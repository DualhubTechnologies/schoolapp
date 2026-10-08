<?php

namespace App\Services;

use App\Models\FeeStructure;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\StudentDiscount;
use App\Models\Term;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Puts charges onto student accounts.
 *
 * Reconciles at the level of the INDIVIDUAL FEE, so:
 *
 *   Run it once  -> every applicable fee is charged.
 *   Run it again -> nothing happens. No duplicates.
 *   Add a fee next week, run it again -> the new fee is charged.
 *
 * Which fees apply:
 *
 *   per_term    the fee started in this term or an earlier one, and no
 *               newer version has replaced it (FeeStructure::termlyFor)
 *   once        the student has never been charged it
 *   on_demand   never automatic — billed deliberately to a class or student
 *
 * And to whom:
 *
 *   applies_to        all | new_only
 *   residency_type_id null = everyone; set = only that residency
 *
 * HOW DISCOUNTS ARE APPLIED
 *
 * A fixed discount covering the whole bill is a POOL spent once across all
 * the fees, not a reduction repeated on each one. A 70,000 hardship waiver
 * against tuition + admission + boarding takes 70,000 off the total, not
 * 70,000 off each line.
 *
 * A percentage discount applies to every line it covers, because half off
 * the total and half off each line come to the same figure.
 *
 * A discount scoped to one fee applies to that fee alone, with no pooling.
 *
 * A FULL BURSARY waives every fee outright, whatever it is scoped to.
 *
 * TRANSPORT
 *
 * A learner on a van route (Student::transportRoute) is charged that
 * route's fare once per term, as its own line linked to the route. It is
 * not a fee structure and takes no discounts: bursaries cover school fees,
 * not the van. A learner with no route is brought by the parent and pays
 * nothing for transport.
 *
 * Each award also carries a scope — this term, this academic year, or
 * ongoing — and is ignored outside that period, so a lapsed award stops
 * discounting rather than quietly running on.
 *
 * This is why fees are resolved TOGETHER rather than one at a time: the
 * charging path has to know how much of a pool is left as it works down
 * the lines.
 */
class BillingService
{
    /** @var array<int, Collection<int, FeeStructure>> term id => termly fees in force */
    protected array $termlyCache = [];

    /**
     * A term that has started but not been billed: it is the current term,
     * the school has active learners in classes, and none of them has been
     * charged a fee for it yet. Prompts the bursar (dashboard and bell).
     */
    public function termNeedsBilling(Term $term): bool
    {
        $hasLearners = Student::where('school_id', $term->school_id)
            ->where('status', 'active')
            ->whereNotNull('school_class_id')
            ->exists();

        return $term->is_current
            && $hasLearners
            && ! StudentCharge::where('school_id', $term->school_id)
                ->where('term_id', $term->getKey())
                ->whereNotNull('fee_structure_id')
                ->exists();
    }

    /**
     * Set the termly fees for a term before billing it. Each changed
     * amount becomes a new version of the fee starting in this term, so
     * earlier terms keep the amount they were billed at (the Fee structure
     * sheet shows any term as it was). A fee already starting in this term
     * is simply corrected.
     *
     * @param  array<int, float|int|string>  $amounts  fee structure id => new amount
     * @return int fees changed
     */
    public function setTermFees(Term $term, array $amounts): int
    {
        $changed = 0;

        DB::transaction(function () use ($term, $amounts, &$changed): void {
            foreach (FeeStructure::termlyFor($term) as $fee) {
                if (! array_key_exists($fee->getKey(), $amounts)) {
                    continue;
                }

                $amount = round((float) $amounts[$fee->getKey()], 2);

                if ($amount <= 0 || abs($amount - (float) $fee->amount) < 0.005) {
                    continue;
                }

                if ((int) $fee->term_id === (int) $term->getKey()) {
                    $fee->update(['amount' => $amount]);
                } else {
                    $fee->replicate()->forceFill(['term_id' => $term->getKey(), 'amount' => $amount])->save();
                }

                $changed++;
            }
        });

        return $changed;
    }

    /**
     * Bill a term: charge every applicable fee to every active student.
     *
     * @param  array<int>|null  $classIds  null = every class
     * @return array{students_examined: int, charges_added: int, students_changed: int}
     */
    public function billTerm(Term $term, ?array $classIds = null): array
    {
        $students = Student::query()
            ->with(['residencyType', 'transportRoute'])
            ->where('school_id', $term->school_id)
            ->where('status', 'active')
            ->whereNotNull('school_class_id')
            ->when($classIds, fn ($q) => $q->whereIn('school_class_id', $classIds))
            ->get();

        $chargesAdded = 0;
        $studentsChanged = 0;

        foreach ($students as $student) {
            $added = $this->billStudent($student, $term);

            if ($added > 0) {
                $chargesAdded += $added;
                $studentsChanged++;
            }
        }

        return [
            'students_examined' => $students->count(),
            'charges_added' => $chargesAdded,
            'students_changed' => $studentsChanged,
        ];
    }

    /**
     * A learner admitted after the current term was billed is billed for
     * it straight away, so their account does not read "fully paid". Does
     * nothing before the term is billed: billing the term covers them.
     *
     * @return float the amount the learner now owes for the term (0 when not billed)
     */
    public function billNewLearner(Student $student): float
    {
        $term = Term::current($student->school_id);

        if (! $term || ! $student->school_class_id || $student->status !== 'active') {
            return 0;
        }

        $termBilled = StudentCharge::where('school_id', $student->school_id)
            ->where('term_id', $term->getKey())
            ->whereNotNull('fee_structure_id')
            ->where('student_id', '!=', $student->getKey())
            ->exists();

        if (! $termBilled) {
            return 0;
        }

        $before = $student->balance();
        $this->billStudent($student, $term);

        return max(0, $student->balance() - $before);
    }

    /**
     * Charge one student every fee that applies to them and is not already
     * on their account, resolving discounts across the whole set.
     *
     * @return int how many charges were added
     */
    public function billStudent(Student $student, Term $term): int
    {
        $fees = $this->applicableFees($student, $term)
            ->reject(fn (FeeStructure $fee) => $this->alreadyChargedInTerm($student, $fee, $term))
            ->values();

        $transport = $this->billTransport($student, $term);

        if ($fees->isEmpty()) {
            return $transport;
        }

        return $transport + DB::transaction(function () use ($student, $term, $fees) {
            $lines = $this->resolveDiscounts($student, $term, $fees);

            foreach ($lines as $line) {
                StudentCharge::create([
                    'school_id' => $student->school_id,
                    'student_id' => $student->getKey(),
                    'term_id' => $term->getKey(),
                    'fee_structure_id' => $line['fee']->getKey(),
                    'description' => $line['fee']->name,
                    'amount' => $line['amount'],
                    'discount_amount' => $line['discount'],
                    'discount_reason' => $line['reason'],
                    'charged_on' => now()->toDateString(),
                    'currency' => $line['fee']->currency ?: 'UGX',
                    'created_by' => auth()->user()?->name,
                ]);
            }

            return $lines->count();
        });
    }

    /**
     * Charge the learner's van fare for the term, once. A learner who joins
     * a route mid-term is charged when the term is billed again.
     *
     * @return int 1 when a transport charge was added, otherwise 0
     */
    public function billTransport(Student $student, Term $term): int
    {
        $route = $student->transportRoute;

        if (! $route || ! $route->is_active || $this->transportChargedInTerm($student, $term)) {
            return 0;
        }

        StudentCharge::create([
            'school_id' => $student->school_id,
            'student_id' => $student->getKey(),
            'term_id' => $term->getKey(),
            'transport_route_id' => $route->getKey(),
            'description' => $route->chargeDescription($student->transport_trip),
            'amount' => $route->fareFor($student->transport_trip),
            'discount_amount' => 0,
            'charged_on' => now()->toDateString(),
            'currency' => 'UGX',
            'created_by' => auth()->user()?->name,
        ]);

        return 1;
    }

    /**
     * Whether the learner already has a van charge this term, on any route
     * (a route change mid-term is settled by the bursar, not re-billed).
     */
    public function transportChargedInTerm(Student $student, Term $term): bool
    {
        return StudentCharge::where('student_id', $student->getKey())
            ->where('term_id', $term->getKey())
            ->whereNotNull('transport_route_id')
            ->exists();
    }

    /**
     * Work out the discount on each fee, treating a whole-bill fixed
     * discount as a pool consumed across the lines in charge order.
     *
     * @param  Collection<int, FeeStructure>  $fees
     * @return Collection<int, array{fee: FeeStructure, amount: float, discount: float, reason: ?string}>
     */
    protected function resolveDiscounts(Student $student, Term $term, Collection $fees): Collection
    {
        $discounts = $student->discounts()
            ->where('is_active', true)
            ->get();

        // How much of each whole-bill fixed discount is still unspent.
        // Keyed by discount id so several can run at once.
        $pools = $discounts
            ->filter(fn (StudentDiscount $d) => $d->type === 'fixed' && $d->fee_structure_id === null)
            ->mapWithKeys(fn (StudentDiscount $d) => [$d->getKey() => (float) $d->value]);

        return $fees->map(function (FeeStructure $fee) use ($term, $discounts, &$pools) {
            $amount = (float) $fee->amount;
            $discountTotal = 0.0;
            $reasons = [];

            foreach ($discounts as $discount) {
                if (! $discount->appliesTo($fee->getKey(), $term->getKey(), $term)) {
                    continue;
                }

                $remaining = $amount - $discountTotal;

                if ($remaining <= 0) {
                    break;
                }

                $isPooled = $discount->type === 'fixed' && $discount->fee_structure_id === null;

                if ($isPooled) {
                    $available = (float) ($pools[$discount->getKey()] ?? 0);

                    if ($available <= 0) {
                        // Already spent on an earlier line.
                        continue;
                    }

                    $value = min($available, $remaining);
                    $pools[$discount->getKey()] = $available - $value;
                } else {
                    // Percentages, and fixed discounts tied to one fee,
                    // apply to this line in full.
                    $value = $discount->amountFor($remaining);
                }

                if ($value <= 0) {
                    continue;
                }

                $discountTotal += $value;
                $reasons[] = $discount->label();
            }

            return [
                'fee' => $fee,
                'amount' => $amount,
                'discount' => min($discountTotal, $amount),
                'reason' => $reasons ? implode(', ', $reasons) : null,
            ];
        });
    }

    /**
     * Bill one fee — or a typed-in charge — to a group of students.
     *
     * @param  array<int>  $studentIds
     * @return array{charged: int, skipped: int}
     */
    public function billStudents(
        Term $term,
        array $studentIds,
        ?FeeStructure $fee = null,
        ?string $description = null,
        ?float $amount = null,
    ): array {
        if (! $fee && ($description === null || $amount === null)) {
            throw new \InvalidArgumentException('Provide either a fee structure or a description and amount.');
        }

        $students = Student::query()
            ->whereIn('id', $studentIds)
            ->where('school_id', $term->school_id)
            ->get();

        $charged = 0;
        $skipped = 0;

        foreach ($students as $student) {
            if ($fee && $this->alreadyChargedInTerm($student, $fee, $term)) {
                $skipped++;

                continue;
            }

            if ($fee) {
                // A single fee added on its own still respects a pooled
                // discount: whatever the student's earlier charges already
                // used is taken into account.
                $lines = $this->resolveDiscounts($student, $term, collect([$fee]));
                $line = $lines->first();

                StudentCharge::create([
                    'school_id' => $student->school_id,
                    'student_id' => $student->getKey(),
                    'term_id' => $term->getKey(),
                    'fee_structure_id' => $fee->getKey(),
                    'description' => $fee->name,
                    'amount' => $line['amount'],
                    'discount_amount' => $this->capPooledDiscount($student, $term, $line['discount']),
                    'discount_reason' => $line['reason'],
                    'charged_on' => now()->toDateString(),
                    'currency' => $fee->currency ?: 'UGX',
                    'created_by' => auth()->user()?->name,
                ]);
            } else {
                $this->addCharge($student, $term, $description, $amount);
            }

            $charged++;
        }

        return ['charged' => $charged, 'skipped' => $skipped];
    }

    /**
     * A one-off charge typed in by the bursar — lost book, replacement tie.
     * No discount: an ad-hoc charge is a specific amount the bursar decided.
     */
    public function addCharge(
        Student $student,
        Term $term,
        string $description,
        float $amount,
        ?int $feeStructureId = null,
    ): StudentCharge {
        return StudentCharge::create([
            'school_id' => $student->school_id,
            'student_id' => $student->getKey(),
            'term_id' => $term->getKey(),
            'fee_structure_id' => $feeStructureId,
            'description' => $description,
            'amount' => $amount,
            'discount_amount' => 0,
            'charged_on' => now()->toDateString(),
            'created_by' => auth()->user()?->name,
        ]);
    }

    /**
     * Stop a pooled discount being given twice when a fee is billed
     * separately after the main run: subtract what the student's existing
     * charges have already taken.
     */
    protected function capPooledDiscount(Student $student, Term $term, float $wanted): float
    {
        if ($wanted <= 0) {
            return 0.0;
        }

        $poolTotal = (float) $student->discounts()
            ->where('is_active', true)
            ->where('type', 'fixed')
            ->whereNull('fee_structure_id')
            ->where(fn ($q) => $q->whereNull('term_id')->orWhere('term_id', $term->getKey()))
            ->sum('value');

        if ($poolTotal <= 0) {
            return $wanted;
        }

        $alreadyGiven = (float) StudentCharge::where('student_id', $student->getKey())
            ->where('term_id', $term->getKey())
            ->sum('discount_amount');

        return max(min($wanted, $poolTotal - $alreadyGiven), 0.0);
    }

    // ── Which fees apply ──

    /**
     * @return Collection<int, FeeStructure>
     */
    public function applicableFees(Student $student, Term $term): Collection
    {
        // Termly fees carry forward from the term they start in -- see
        // FeeStructure::termlyFor(). Resolved once per term, not per student.
        $termly = ($this->termlyCache[$term->getKey()] ??= FeeStructure::termlyFor($term))
            ->where('school_class_id', $student->school_class_id);

        $once = FeeStructure::query()
            ->where('school_id', $student->school_id)
            ->where('school_class_id', $student->school_class_id)
            ->where('is_active', true)
            ->where('frequency', 'once')
            ->get();

        return $termly->concat($once)
            ->sortBy('id')
            ->filter(function (FeeStructure $fee) use ($student, $term) {
                if ($fee->frequency === 'once' && $this->alreadyChargedEver($student, $fee)) {
                    return false;
                }

                if (! $fee->appliesToResidency($student->residency_type_id)) {
                    return false;
                }

                if ($fee->applies_to === 'new_only' && ! $this->isNewThisTerm($student, $term, $fee)) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    // ── Duplicate checks ──

    protected function alreadyChargedEver(Student $student, FeeStructure $fee): bool
    {
        return StudentCharge::where('student_id', $student->getKey())
            ->where('fee_structure_id', $fee->getKey())
            ->exists();
    }

    protected function alreadyChargedInTerm(Student $student, FeeStructure $fee, Term $term): bool
    {
        if ($fee->frequency === 'once') {
            return $this->alreadyChargedEver($student, $fee);
        }

        return StudentCharge::where('student_id', $student->getKey())
            ->where('term_id', $term->getKey())
            ->where('fee_structure_id', $fee->getKey())
            ->exists();
    }

    /**
     * "New" means admitted during this term AND never charged this fee.
     */
    protected function isNewThisTerm(Student $student, Term $term, FeeStructure $fee): bool
    {
        if ($this->alreadyChargedEver($student, $fee)) {
            return false;
        }

        if (! $student->admission_date || ! $term->start_date || ! $term->end_date) {
            return false;
        }

        return $student->admission_date->betweenIncluded($term->start_date, $term->end_date);
    }
}
