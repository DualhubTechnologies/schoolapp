<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollEntry extends Model
{
    protected $fillable = [
        'payroll_period_id',
        'staff_id',
        'base_salary',
        'total_allowances',
        'gross_pay',
        'taxable_income',
        'lst',
        'total_deductions',
        'nssf_employee',
        'nssf_employer',
        'paye',
        'total_statutory',
        'arrears_amount',
        'net_pay',
        'status',
        'adjustment_notes',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'total_allowances' => 'decimal:2',
            'gross_pay' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'nssf_employee' => 'decimal:2',
            'nssf_employer' => 'decimal:2',
            'paye' => 'decimal:2',
            'lst' => 'decimal:2',
            'taxable_income' => 'decimal:2',
            'total_statutory' => 'decimal:2',
            'arrears_amount' => 'decimal:2',
            'net_pay' => 'decimal:2',
        ];
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollEntryItem::class);
    }

    public function allowanceItems(): HasMany
    {
        return $this->items()->where('category', 'allowance');
    }

    public function deductionItems(): HasMany
    {
        return $this->items()->where('category', 'deduction');
    }

    public function statutoryItems(): HasMany
    {
        return $this->items()->where('category', 'statutory');
    }

    public function arrearsItems(): HasMany
    {
        return $this->items()->where('category', 'arrears');
    }
}
