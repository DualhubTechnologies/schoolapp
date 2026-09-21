<?php

namespace App\Services\Payroll;

use App\Models\PayeTaxBracket;
use App\Models\SalaryArrear;
use App\Models\Staff;
use App\Models\StaffAllowance;
use App\Models\StaffDeduction;
use App\Models\StaffSalary;
use Illuminate\Support\Carbon;

/**
 * Works out one staff member's pay for one month under Ugandan rules.
 * Pure calculation: reads the staff member's salary set-up, writes nothing.
 *
 *   Gross        basic + allowances + approved arrears
 *   NSSF         5% employee (deducted) and 10% employer (school's cost),
 *                on gross
 *   PAYE         URA monthly bands, on gross TAXABLE pay (basic + taxable
 *                allowances + arrears). Employee NSSF is not deducted
 *                first -- it is not an allowable deduction in Uganda.
 *   LST          annual Local Service Tax by pay band, in equal
 *                instalments over July–October
 *   Deductions   loans, advances, SACCO, welfare... in force that month
 *   Net          gross − NSSF − PAYE − LST − deductions
 *
 * All amounts are whole shillings.
 */
class PayrollCalculator
{
    /**
     * @return array{entry: array<string, float>, items: list<array<string, mixed>>, arrears: \Illuminate\Support\Collection, deductions: \Illuminate\Support\Collection}|null
     *         null when the staff member has no salary for the month
     */
    public function forMonth(Staff $staff, int $month, int $year, string $country = 'UG'): ?array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        $salary = StaffSalary::where('staff_id', $staff->getKey())
            ->whereDate('effective_from', '<=', $end)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $start))
            ->orderByDesc('effective_from')
            ->first();

        if (! $salary) {
            return null;
        }

        $basic = $this->whole($salary->base_salary);
        $items = [];

        // ── Allowances ──
        $allowanceTotal = 0;
        $taxableAllowances = 0;

        foreach (StaffAllowance::where('staff_id', $staff->getKey())->where('is_active', true)->with('allowanceType')->get() as $allowance) {
            $amount = $this->whole($allowance->amount);
            $allowanceTotal += $amount;

            if ($allowance->allowanceType?->is_taxable ?? true) {
                $taxableAllowances += $amount;
            }

            $items[] = $this->item('allowance', $allowance->allowanceType?->name ?? 'Allowance', $amount, 'staff_allowance', $allowance->getKey());
        }

        // ── Arrears approved for payment ──
        $arrears = SalaryArrear::where('staff_id', $staff->getKey())
            ->where('status', 'approved')
            ->whereNull('applied_in_period_id')
            ->get();

        $arrearsTotal = 0;

        foreach ($arrears as $arrear) {
            $amount = $this->whole($arrear->amount);
            $arrearsTotal += $amount;
            $items[] = $this->item('arrears', 'Arrears: ' . $arrear->reason, $amount, 'salary_arrear', $arrear->getKey());
        }

        $arrearsTaxable = (bool) config('payroll.arrears_are_taxable', true);

        $gross = $basic + $allowanceTotal + ($arrearsTaxable ? $arrearsTotal : 0);
        $regularPay = $basic + $allowanceTotal; // what LST bands are judged on
        $taxable = $basic + $taxableAllowances + ($arrearsTaxable ? $arrearsTotal : 0);

        // ── NSSF ──
        $nssfEmployee = $staff->pays_nssf ? $this->whole($gross * config('payroll.nssf.employee_rate', 5) / 100) : 0;
        $nssfEmployer = $staff->pays_nssf ? $this->whole($gross * config('payroll.nssf.employer_rate', 10) / 100) : 0;

        // ── PAYE ──
        $payeBase = $taxable - (config('payroll.deduct_nssf_before_paye') ? $nssfEmployee : 0);
        $paye = $this->whole(PayeTaxBracket::calculatePaye(max($payeBase, 0), $country));

        // ── LST ──
        $lst = $staff->pays_lst ? $this->lstInstalment($regularPay, $month) : 0;

        if ($nssfEmployee > 0) {
            $items[] = $this->item('statutory', 'NSSF (' . config('payroll.nssf.employee_rate', 5) . '%)', $nssfEmployee);
        }
        if ($paye > 0) {
            $items[] = $this->item('statutory', 'PAYE', $paye);
        }
        if ($lst > 0) {
            $items[] = $this->item('statutory', 'Local Service Tax', $lst);
        }

        // ── Other deductions in force this month ──
        $deductions = StaffDeduction::where('staff_id', $staff->getKey())
            ->where('is_active', true)
            ->with('deductionType')
            ->get()
            ->filter(fn (StaffDeduction $d) => ! $d->deductionType?->is_statutory && $d->appliesInMonth($start, $end));

        $deductionTotal = 0;
        $deductionAmounts = collect();

        foreach ($deductions as $deduction) {
            $amount = $this->whole($deduction->calculateAmount($gross));

            if ($amount <= 0) {
                continue;
            }

            $deductionTotal += $amount;
            $deductionAmounts->put($deduction->getKey(), $amount);

            $label = $deduction->deductionType?->name ?? 'Deduction';
            if ($deduction->total_amount) {
                $left = max((float) $deduction->total_amount - (float) $deduction->amount_recovered - $amount, 0);
                $label .= ' (balance after: ' . number_format($left) . ')';
            }

            $items[] = $this->item('deduction', $label, $amount, 'staff_deduction', $deduction->getKey());
        }

        $statutory = $nssfEmployee + $paye + $lst;
        $net = $gross - $statutory - $deductionTotal + ($arrearsTaxable ? 0 : $arrearsTotal);

        return [
            'entry' => [
                'base_salary' => $basic,
                'total_allowances' => $allowanceTotal,
                'gross_pay' => $gross,
                'taxable_income' => $taxable,
                'total_deductions' => $deductionTotal,
                'nssf_employee' => $nssfEmployee,
                'nssf_employer' => $nssfEmployer,
                'paye' => $paye,
                'lst' => $lst,
                'total_statutory' => $statutory,
                'arrears_amount' => $arrearsTotal,
                'net_pay' => $net,
            ],
            'items' => $items,
            'arrears' => $arrears,
            'deductions' => $deductionAmounts, // staff_deduction id => amount taken
        ];
    }

    /**
     * This month's Local Service Tax instalment: the annual amount for the
     * pay band, split over the collection months.
     */
    public function lstInstalment(float $monthlyPay, int $month): int
    {
        $months = config('payroll.lst.months', []);

        if (! config('payroll.lst.enabled', true) || ! in_array($month, $months, true)) {
            return 0;
        }

        $annual = 0;

        foreach (config('payroll.lst.bands', []) as $above => $amount) {
            if ($monthlyPay > $above) {
                $annual = $amount;
            }
        }

        return $this->whole($annual / max(count($months), 1));
    }

    protected function item(string $category, string $name, float $amount, ?string $refType = null, ?int $refId = null): array
    {
        return [
            'category' => $category,
            'name' => $name,
            'amount' => $amount,
            'reference_type' => $refType,
            'reference_id' => $refId,
        ];
    }

    protected function whole($amount): int
    {
        return (int) round((float) $amount);
    }
}
