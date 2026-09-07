<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryArrear extends Model
{
    protected $fillable = [
        'school_id',
        'staff_id',
        'amount',
        'reason',
        'month',
        'year',
        'status',
        'applied_in_period_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function appliedInPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'applied_in_period_id');
    }
}
