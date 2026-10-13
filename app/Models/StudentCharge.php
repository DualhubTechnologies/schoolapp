<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One charge on a student's account.
 *
 * Replaces the old invoices + invoice_items pair. Since the invoice is
 * printed on demand from the ledger rather than stored, there was no
 * document to keep -- only the charges themselves.
 *
 * An opening balance (is_opening_balance) is what the learner owed in the
 * school's previous system, brought in by OpeningBalanceCsvImporter: one
 * per learner, below zero when they were in credit. includes_term_fees
 * says the old system had already charged that term's fees, so billing
 * leaves the learner's term alone.
 */
class StudentCharge extends Model
{
    use Auditable;
    use HasFactory;

    protected $fillable = [
        'school_id',
        'student_id',
        'term_id',
        'fee_structure_id',
        'transport_route_id',
        'description',
        'amount',
        'discount_amount',
        'discount_reason',
        'charged_on',
        'currency',
        'created_by',
        'notes',
        'is_opening_balance',
        'includes_term_fees',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'charged_on' => 'date',
            'is_opening_balance' => 'boolean',
            'includes_term_fees' => 'boolean',
        ];
    }

    /**
     * Charges that show a term has been billed: a fee from the fee
     * structure, or an opening balance that already includes the term's
     * fees.
     *
     * @param  Builder<StudentCharge>  $query
     */
    public function scopeTermBilling(Builder $query): void
    {
        $query->where(fn (Builder $charges) => $charges->whereNotNull('fee_structure_id')
            ->orWhere(fn (Builder $opening) => $opening->where('is_opening_balance', true)->where('includes_term_fees', true)));
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * The van route, for a transport charge; null for school fees.
     *
     * @return BelongsTo<TransportRoute, $this>
     */
    public function transportRoute(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }

    /**
     * What this charge actually costs the parent.
     */
    public function netAmount(): float
    {
        return (float) $this->amount - (float) $this->discount_amount;
    }

    public function formattedAmount(): string
    {
        return $this->currency.' '.number_format($this->netAmount(), 0);
    }
}
