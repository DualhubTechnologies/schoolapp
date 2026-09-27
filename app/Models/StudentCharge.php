<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One charge on a student's account.
 *
 * Replaces the old invoices + invoice_items pair. Since the invoice is
 * printed on demand from the ledger rather than stored, there was no
 * document to keep -- only the charges themselves.
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
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'charged_on' => 'date',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

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
