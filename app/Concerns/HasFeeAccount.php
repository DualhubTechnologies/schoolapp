<?php

namespace App\Concerns;

use App\Models\StudentCharge;
use App\Models\StudentDiscount;
use App\Models\Term;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * The student's fee account.
 *
 * A running ledger: everything ever charged, minus everything ever paid.
 * Arrears are never re-charged — they are simply the part of the balance
 * that predates the current term. That is what stops old debt being
 * counted twice.
 */
trait HasFeeAccount
{
    public function charges(): HasMany
    {
        return $this->hasMany(StudentCharge::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(StudentDiscount::class);
    }

    // ── The ledger ──

    /**
     * Everything ever charged, after discounts.
     */
    public function totalCharged(): float
    {
        return (float) $this->charges()
            ->sum(DB::raw('amount - discount_amount'));
    }

    /**
     * What the student still owes. Negative means they are in credit.
     */
    public function balance(): float
    {
        return $this->totalCharged() - $this->totalPaid();
    }

    /**
     * Credit on the account as a positive figure; zero when they owe money.
     */
    public function creditBalance(): float
    {
        $balance = $this->balance();

        return $balance < 0 ? abs($balance) : 0.0;
    }

    /**
     * Percentage of the TOTAL owed that has been paid, arrears included,
     * so 100% always means "owes nothing".
     *
     * A student with no charges counts as fully paid — they owe nothing.
     */
    public function percentagePaid(): float
    {
        $charged = $this->totalCharged();

        if ($charged <= 0) {
            return 100.0;
        }

        return round(min(($this->totalPaid() / $charged) * 100, 100), 1);
    }

    public function hasClearedFees(): bool
    {
        return $this->balance() <= 0;
    }

    /**
     * Charges for one term, after discounts.
     */
    public function chargedInTerm(Term $term): float
    {
        return (float) $this->charges()
            ->where('term_id', $term->getKey())
            ->sum(DB::raw('amount - discount_amount'));
    }

    /**
     * Unpaid balance from terms before the one given — the figure shown as
     * "brought forward" at the top of a statement.
     *
     * Every payment counts against the oldest debt first, which is what
     * schools do in practice.
     */
    public function arrearsBefore(Term $term): float
    {
        $chargedBefore = (float) DB::table('student_charges')
            ->join('terms', 'student_charges.term_id', '=', 'terms.id')
            ->where('student_charges.student_id', $this->getKey())
            ->where(function ($q) use ($term) {
                $q->where('terms.academic_year_id', '<', $term->academic_year_id)
                    ->orWhere(function ($q2) use ($term) {
                        $q2->where('terms.academic_year_id', $term->academic_year_id)
                            ->where('terms.sequence', '<', $term->sequence);
                    });
            })
            ->sum(DB::raw('student_charges.amount - student_charges.discount_amount'));

        return max($chargedBefore - $this->totalPaid(), 0.0);
    }

    /**
     * Discounts in force, optionally narrowed to one fee and term.
     */
    public function activeDiscounts(?int $feeStructureId = null, ?int $termId = null)
    {
        return $this->discounts()
            ->where('is_active', true)
            ->get()
            ->filter(fn (StudentDiscount $d) => $d->appliesTo($feeStructureId, $termId));
    }
}
