<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentPayment;
use App\Models\Term;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The student's financial record.
 *
 * DERIVED, never stored: charges and payments merged into one list in date
 * order with a running balance. Deriving it means it cannot drift out of
 * step with reality — correct a charge or delete a payment and the ledger
 * is immediately right, with no second copy of the truth to reconcile.
 *
 *   Ledger     every line, all terms — the bursar's working record
 *   Statement  a term's lines, summarised — what a parent receives
 *   Invoice    the current balance as a document — printed on demand
 */
class StudentLedger
{
    /**
     * Every transaction, oldest first, with a running balance.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function entries(Student $student, ?Term $term = null): Collection
    {
        $entries = $this->charges($student)
            ->concat($this->payments($student))
            // Same-day ordering: charges before payments, so a payment
            // reads as settling a charge rather than preceding it.
            ->sortBy([
                fn ($a, $b) => $a['date'] <=> $b['date'],
                fn ($a, $b) => ($a['type'] === 'charge' ? 0 : 1) <=> ($b['type'] === 'charge' ? 0 : 1),
            ])
            ->values();

        // The running balance is computed over the WHOLE history even when
        // only one term is wanted, or a term view would start from zero and
        // hide what came before.
        $balance = 0.0;

        $entries = $entries->map(function (array $entry) use (&$balance) {
            $balance += $entry['debit'] - $entry['credit'];
            $entry['balance'] = $balance;

            return $entry;
        });

        return $term
            ? $entries->where('term_id', $term->getKey())->values()
            : $entries;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function charges(Student $student): Collection
    {
        return DB::table('student_charges')
            ->leftJoin('terms', 'student_charges.term_id', '=', 'terms.id')
            ->where('student_charges.student_id', $student->getKey())
            ->select([
                'student_charges.id',
                'student_charges.description',
                'student_charges.amount',
                'student_charges.discount_amount',
                'student_charges.discount_reason',
                'student_charges.charged_on',
                'student_charges.term_id',
                'terms.name as term_name',
            ])
            ->get()
            ->map(fn ($row) => [
                'date' => $row->charged_on,
                'type' => 'charge',
                'description' => $row->description,
                'reference' => null,
                'term_id' => $row->term_id,
                'term_name' => $row->term_name,
                'debit' => (float) $row->amount - (float) $row->discount_amount,
                'credit' => 0.0,
                'discount' => (float) $row->discount_amount,
                'discount_reason' => $row->discount_reason,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function payments(Student $student): Collection
    {
        return DB::table('student_payments')
            ->leftJoin('terms', 'student_payments.term_id', '=', 'terms.id')
            ->where('student_payments.student_id', $student->getKey())
            ->whereNull('student_payments.voided_at')
            ->select([
                'student_payments.id',
                'student_payments.receipt_no',
                'student_payments.amount',
                'student_payments.paid_on',
                'student_payments.method',
                'student_payments.reference',
                'student_payments.term_id',
                'terms.name as term_name',
            ])
            ->get()
            ->map(fn ($row) => [
                'date' => $row->paid_on,
                'type' => 'payment',
                'description' => 'Payment received'
                    .(($label = StudentPayment::METHODS[$row->method] ?? null) ? " — {$label}" : ''),
                'reference' => $row->receipt_no.($row->reference ? " · {$row->reference}" : ''),
                'term_id' => $row->term_id,
                'term_name' => $row->term_name,
                'debit' => 0.0,
                'credit' => (float) $row->amount,
                'discount' => 0.0,
                'discount_reason' => null,
            ]);
    }

    /**
     * @return array{charged: float, paid: float, balance: float, percent: float}
     */
    public function summary(Student $student): array
    {
        $charged = $student->totalCharged();
        $paid = $student->totalPaid();

        return [
            'charged' => $charged,
            'paid' => $paid,
            'balance' => $charged - $paid,
            'percent' => $student->percentagePaid(),
        ];
    }

    /**
     * Totals for one term, plus what was owed when the term opened.
     *
     * @return array{opening: float, charged: float, paid: float, closing: float}
     */
    public function termSummary(Student $student, Term $term): array
    {
        $all = $this->entries($student);
        $inTerm = $all->where('term_id', $term->getKey());

        $charged = (float) $inTerm->sum('debit');
        $paid = (float) $inTerm->sum('credit');

        $firstIndex = $all->search(fn ($e) => $e['term_id'] === $term->getKey());

        $opening = $firstIndex === false || $firstIndex === 0
            ? 0.0
            : (float) $all[$firstIndex - 1]['balance'];

        return [
            'opening' => $opening,
            'charged' => $charged,
            'paid' => $paid,
            'closing' => $opening + $charged - $paid,
        ];
    }
}
