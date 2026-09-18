<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentDiscount extends Model
{
    use HasFactory;
    use Auditable;

    protected $fillable = [
        'school_id',
        'student_id',
        'fee_structure_id',
        'term_id',
        'reason',
        'type',
        'value',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public const TYPES = [
        'percentage' => 'Percentage (%)',
        'fixed' => 'Fixed amount',
    ];

    public const REASONS = [
        'scholarship' => 'Scholarship / bursary',
        'staff_child' => 'Staff child',
        'sibling' => 'Sibling discount',
        'hardship' => 'Hardship waiver',
        'other' => 'Other',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * Does this discount apply to the given fee in the given term?
     *
     * A discount with no fee_structure_id applies to every fee.
     * A discount with no term_id applies every term.
     */
    public function appliesTo(?int $feeStructureId, ?int $termId): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->fee_structure_id && $this->fee_structure_id !== $feeStructureId) {
            return false;
        }

        if ($this->term_id && $this->term_id !== $termId) {
            return false;
        }

        return true;
    }

    /**
     * The shilling value of this discount against a given charge.
     * Never returns more than the charge itself -- a discount cannot
     * turn a bill into a credit.
     */
    public function amountFor(float $charge): float
    {
        $discount = $this->type === 'percentage'
            ? $charge * ((float) $this->value / 100)
            : (float) $this->value;

        return (float) min($discount, $charge);
    }

    public function label(): string
    {
        $value = $this->type === 'percentage'
            ? rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.') . '%'
            : number_format((float) $this->value, 0);

        return (self::REASONS[$this->reason] ?? $this->reason) . " ({$value})";
    }
}
