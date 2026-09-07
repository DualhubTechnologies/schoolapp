<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class PayrollPeriod extends Model
{
    protected $fillable = [
        'school_id',
        'month',
        'year',
        'status',
        'generated_by',
        'approved_by',
        'approved_at',
        'paid_at',
        'total_gross',
        'total_allowances',
        'total_deductions',
        'total_statutory',
        'total_net',
        'total_employer_nssf',
        'staff_count',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_gross' => 'decimal:2',
            'total_allowances' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_statutory' => 'decimal:2',
            'total_net' => 'decimal:2',
            'total_employer_nssf' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }

    /**
     * Get the display label for this period (e.g. "September 2026")
     */
    public function getPeriodLabelAttribute(): string
    {
        $months = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ];

        return ($months[$this->month] ?? 'Unknown') . ' ' . $this->year;
    }

    /**
     * Get the previous month and year relative to this period.
     */
    protected function getPreviousPeriod(): ?self
    {
        $prevMonth = $this->month - 1;
        $prevYear = $this->year;

        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear--;
        }

        return self::where('school_id', $this->school_id)
            ->where('month', $prevMonth)
            ->where('year', $prevYear)
            ->first();
    }

    /**
     * Detect staff members who were NOT paid in the previous month
     * and auto-create pending salary arrears for them.
     *
     * Returns the number of arrears created.
     */
    public function detectMissedPayments(): int
    {
        $previousPeriod = $this->getPreviousPeriod();

        // No previous payroll period exists — this is the first month, nothing to compare against
        if (! $previousPeriod) {
            return 0;
        }

        // Get IDs of staff who WERE paid in the previous period
        $paidStaffIds = $previousPeriod->entries()
            ->where('status', 'included')
            ->pluck('staff_id')
            ->toArray();

        // Get all staff who SHOULD have been paid (active, employed before the previous period ended)
        $previousMonthEnd = \Carbon\Carbon::create($previousPeriod->year, $previousPeriod->month)->endOfMonth();

        $shouldHaveBeenPaid = Staff::where('school_id', $this->school_id)
            ->where('status', 'active')
            ->where('employment_date', '<=', $previousMonthEnd)
            ->whereHas('salaries', function ($query) {
                $query->whereNull('effective_to'); // has a current salary record
            })
            ->whereNotIn('id', $paidStaffIds)
            ->get();

        $months = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ];

        $previousMonthName = ($months[$previousPeriod->month] ?? 'Unknown') . ' ' . $previousPeriod->year;
        $count = 0;

        foreach ($shouldHaveBeenPaid as $staff) {
            // Check if an arrear already exists for this staff for that month (avoid duplicates)
            $existingArrear = SalaryArrear::where('staff_id', $staff->id)
                ->where('month', $previousPeriod->month)
                ->where('year', $previousPeriod->year)
                ->exists();

            if ($existingArrear) {
                continue;
            }

            // Calculate what they should have been paid (base salary + active allowances)
            $salary = StaffSalary::currentForStaff($staff->id);
            if (! $salary) {
                continue;
            }

            $baseSalary = (float) $salary->base_salary;
            $totalAllowances = StaffAllowance::where('staff_id', $staff->id)
                ->where('is_active', true)
                ->sum('amount');

            $grossAmount = $baseSalary + $totalAllowances;

            SalaryArrear::create([
                'school_id' => $this->school_id,
                'staff_id' => $staff->id,
                'amount' => $grossAmount,
                'reason' => "Unpaid salary — {$previousMonthName}",
                'month' => $previousPeriod->month,
                'year' => $previousPeriod->year,
                'status' => 'pending',
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Generate payroll entries for all active staff in this school.
     * This is the core payroll generation logic.
     */
    public function generateEntries(): int
    {
        $school = $this->school;
        $country = $school->country ?? 'Uganda';

        // Map country names to codes for PAYE
        $countryCode = match (strtolower($country)) {
            'uganda' => 'UG',
            'kenya' => 'KE',
            'tanzania' => 'TZ',
            'rwanda' => 'RW',
            'south sudan' => 'SS',
            'burundi' => 'BI',
            default => 'UG',
        };

        // Get all active staff for this school
        $activeStaff = Staff::where('school_id', $this->school_id)
            ->where('status', 'active')
            ->get();

        $count = 0;

        DB::transaction(function () use ($activeStaff, $countryCode, &$count) {
            $totalGross = 0;
            $totalAllowances = 0;
            $totalDeductions = 0;
            $totalStatutory = 0;
            $totalNet = 0;
            $totalEmployerNssf = 0;

            foreach ($activeStaff as $staff) {
                // Get current salary
                $salary = StaffSalary::currentForStaff($staff->id);
                if (! $salary) {
                    continue; // Skip staff with no salary record
                }

                $baseSalary = (float) $salary->base_salary;

                // Calculate allowances
                $allowances = StaffAllowance::where('staff_id', $staff->id)
                    ->where('is_active', true)
                    ->with('allowanceType')
                    ->get();

                $totalAllowanceAmount = 0;
                $allowanceItems = [];
                $taxableAllowances = 0;

                foreach ($allowances as $allowance) {
                    $amount = (float) $allowance->amount;
                    $totalAllowanceAmount += $amount;

                    if ($allowance->allowanceType->is_taxable) {
                        $taxableAllowances += $amount;
                    }

                    $allowanceItems[] = [
                        'category' => 'allowance',
                        'name' => $allowance->allowanceType->name,
                        'amount' => $amount,
                        'reference_type' => 'allowance_type',
                        'reference_id' => $allowance->allowance_type_id,
                    ];
                }

                $grossPay = $baseSalary + $totalAllowanceAmount;
                $taxableIncome = $baseSalary + $taxableAllowances;

                // Calculate NSSF (based on gross pay)
                $nssfEmployee = round($grossPay * 0.05, 2);
                $nssfEmployer = round($grossPay * 0.10, 2);

                // Calculate PAYE (on taxable income minus NSSF employee contribution)
                $payeTaxableAmount = $taxableIncome - $nssfEmployee;
                $paye = PayeTaxBracket::calculatePaye($payeTaxableAmount, $countryCode);

                $statutoryTotal = $nssfEmployee + $paye;

                $statutoryItems = [
                    [
                        'category' => 'statutory',
                        'name' => 'NSSF Employee (5%)',
                        'amount' => $nssfEmployee,
                        'reference_type' => null,
                        'reference_id' => null,
                    ],
                    [
                        'category' => 'statutory',
                        'name' => 'PAYE',
                        'amount' => $paye,
                        'reference_type' => null,
                        'reference_id' => null,
                    ],
                ];

                // Calculate non-statutory deductions
                $deductions = StaffDeduction::where('staff_id', $staff->id)
                    ->where('is_active', true)
                    ->with('deductionType')
                    ->get()
                    ->filter(fn ($d) => $d->isApplicable() && ! $d->deductionType->is_statutory);

                $totalDeductionAmount = 0;
                $deductionItems = [];

                foreach ($deductions as $deduction) {
                    $amount = $deduction->calculateAmount($grossPay);
                    $totalDeductionAmount += $amount;

                    $deductionItems[] = [
                        'category' => 'deduction',
                        'name' => $deduction->deductionType->name,
                        'amount' => $amount,
                        'reference_type' => 'deduction_type',
                        'reference_id' => $deduction->deduction_type_id,
                    ];
                }

                // Calculate arrears
                $arrears = SalaryArrear::where('staff_id', $staff->id)
                    ->where('status', 'approved')
                    ->whereNull('applied_in_period_id')
                    ->get();

                $arrearsAmount = 0;
                $arrearItems = [];

                foreach ($arrears as $arrear) {
                    $arrearsAmount += (float) $arrear->amount;

                    $arrearItems[] = [
                        'category' => 'arrears',
                        'name' => "Arrears: {$arrear->reason} ({$arrear->month}/{$arrear->year})",
                        'amount' => (float) $arrear->amount,
                        'reference_type' => 'salary_arrear',
                        'reference_id' => $arrear->id,
                    ];
                }

                // Net pay = gross - statutory - deductions + arrears
                $netPay = $grossPay - $statutoryTotal - $totalDeductionAmount + $arrearsAmount;

                // Create the payroll entry
                $entry = PayrollEntry::create([
                    'payroll_period_id' => $this->id,
                    'staff_id' => $staff->id,
                    'base_salary' => $baseSalary,
                    'total_allowances' => $totalAllowanceAmount,
                    'gross_pay' => $grossPay,
                    'total_deductions' => $totalDeductionAmount,
                    'nssf_employee' => $nssfEmployee,
                    'nssf_employer' => $nssfEmployer,
                    'paye' => $paye,
                    'total_statutory' => $statutoryTotal,
                    'arrears_amount' => $arrearsAmount,
                    'net_pay' => $netPay,
                ]);

                // Create all line items
                $allItems = array_merge($allowanceItems, $statutoryItems, $deductionItems, $arrearItems);
                foreach ($allItems as $item) {
                    $entry->items()->create($item);
                }

                // Mark arrears as applied
                foreach ($arrears as $arrear) {
                    $arrear->update([
                        'status' => 'paid',
                        'applied_in_period_id' => $this->id,
                    ]);
                }

                // Update loan recovery amounts
                foreach ($deductions as $deduction) {
                    if ($deduction->total_amount) {
                        $amount = $deduction->calculateAmount($grossPay);
                        $deduction->increment('amount_recovered', $amount);

                        if ($deduction->amount_recovered >= $deduction->total_amount) {
                            $deduction->update(['is_active' => false]);
                        }
                    }
                }

                // Accumulate totals
                $totalGross += $grossPay;
                $totalAllowances += $totalAllowanceAmount;
                $totalDeductions += $totalDeductionAmount;
                $totalStatutory += $statutoryTotal;
                $totalNet += $netPay;
                $totalEmployerNssf += $nssfEmployer;
                $count++;
            }

            // Update period totals
            $this->update([
                'total_gross' => $totalGross,
                'total_allowances' => $totalAllowances,
                'total_deductions' => $totalDeductions,
                'total_statutory' => $totalStatutory,
                'total_net' => $totalNet,
                'total_employer_nssf' => $totalEmployerNssf,
                'staff_count' => $count,
            ]);
        });

        // After generating current month, detect anyone who was missed last month
        $missedCount = $this->detectMissedPayments();

        return $count;
    }
}