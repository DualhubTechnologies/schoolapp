<?php

namespace App\Models;

use App\Services\Payroll\PayrollService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'total_paye',
        'total_nssf_employee',
        'total_lst',
        'payment_date',
        'payment_method',
        'payment_reference',
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
            'payment_date' => 'date',
            'total_paye' => 'decimal:2',
            'total_nssf_employee' => 'decimal:2',
            'total_lst' => 'decimal:2',
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

        return ($months[$this->month] ?? 'Unknown').' '.$this->year;
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
        $previousMonthEnd = Carbon::create($previousPeriod->year, $previousPeriod->month)->endOfMonth();

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

        $previousMonthName = ($months[$previousPeriod->month] ?? 'Unknown').' '.$previousPeriod->year;
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
     * Calculate this run's payslips. Kept for older callers; the logic
     * lives in PayrollService.
     */
    public function generateEntries(): int
    {
        return app(PayrollService::class)->generate($this)['staff'];
    }

    public const STATUSES = [
        'draft' => 'Draft',
        'approved' => 'Approved',
        'paid' => 'Paid',
    ];

    public const PAYMENT_METHODS = [
        'bank' => 'Bank transfer',
        'mobile_money' => 'Mobile money',
        'cash' => 'Cash',
        'cheque' => 'Cheque',
    ];

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function monthStart(): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::create($this->year, $this->month, 1);
    }
}
