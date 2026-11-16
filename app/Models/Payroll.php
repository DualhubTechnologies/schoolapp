<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    protected $fillable = [
        'school_id',
        'staff_id',
        'period',
        'base_salary',
        'allowances',
        'deductions',
        'net_pay',
        'status',
        'paid_on',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'allowances' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }

    /**
     * The school this payroll record belongs to.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * The staff member this payroll record is for.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}