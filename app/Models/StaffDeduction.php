<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffDeduction extends Model
{
    protected $fillable = [
        'staff_id',
        'deduction_type_id',
        'amount',
        'rate',
        'is_recurring',
        'start_date',
        'end_date',
        'total_amount',
        'amount_recovered',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'rate' => 'decimal:4',
            'is_recurring' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'total_amount' => 'decimal:2',
            'amount_recovered' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function deductionType(): BelongsTo
    {
        return $this->belongsTo(DeductionType::class);
    }

    /**
     * Check if this deduction is still applicable (not ended, not fully recovered).
     */
    public function isApplicable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->end_date && $this->end_date->isPast()) {
            return false;
        }

        // For loans: check if fully recovered
        if ($this->total_amount && $this->amount_recovered >= $this->total_amount) {
            return false;
        }

        return true;
    }

    /**
     * Calculate the actual deduction amount for a given gross salary.
     */
    public function calculateAmount(float $grossSalary): float
    {
        $deductionType = $this->deductionType;

        if ($deductionType->calculation_method === 'percentage') {
            $rate = $this->rate ?? $deductionType->default_rate;
            return round($grossSalary * ($rate / 100), 2);
        }

        // For loans: don't exceed remaining balance
        if ($this->total_amount) {
            $remaining = $this->total_amount - $this->amount_recovered;
            return min($this->amount, $remaining);
        }

        return (float) $this->amount;
    }
}
