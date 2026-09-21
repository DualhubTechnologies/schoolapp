<?php

namespace App\Services\Payroll;

use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\SalaryArrear;
use App\Models\Staff;
use App\Models\StaffDeduction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Runs a month's payroll through its life:
 *
 *   draft     generate() as often as needed -- it only writes the payslips
 *             for this run, so recalculating after fixing a salary is safe
 *   approved  approve() locks the figures and only THEN records loan
 *             recoveries and marks arrears as paid, so a draft that is
 *             thrown away leaves no trace
 *   paid      markPaid() records when and how salaries went out
 *
 * Only a draft can be deleted.
 */
class PayrollService
{
    public function __construct(protected PayrollCalculator $calculator) {}

    /**
     * (Re)calculate every payslip in a draft run.
     *
     * @return array{staff: int, skipped_no_salary: list<string>, missed_last_month: int}
     */
    public function generate(PayrollPeriod $period): array
    {
        abort_unless($period->status === 'draft', 422, 'Only a draft payroll can be recalculated.');

        $country = $this->countryCode($period->school?->country);
        $monthEnd = Carbon::create($period->year, $period->month, 1)->endOfMonth();

        $staff = Staff::where('school_id', $period->school_id)
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('employment_date')->orWhereDate('employment_date', '<=', $monthEnd))
            ->orderBy('name')
            ->get();

        $skipped = [];
        $count = 0;

        DB::transaction(function () use ($period, $staff, $country, &$skipped, &$count) {
            // Start clean: a draft is always a fresh calculation.
            $period->entries()->each(fn (PayrollEntry $e) => $e->delete());

            foreach ($staff as $member) {
                $pay = $this->calculator->forMonth($member, $period->month, $period->year, $country);

                if (! $pay) {
                    $skipped[] = $member->name;

                    continue;
                }

                $entry = $period->entries()->create($pay['entry'] + [
                    'staff_id' => $member->getKey(),
                    'status' => 'included',
                ]);

                $entry->items()->createMany($pay['items']);
                $count++;
            }

            $this->refreshTotals($period);
            $period->update(['generated_by' => auth()->id()]);
        });

        return [
            'staff' => $count,
            'skipped_no_salary' => $skipped,
            'missed_last_month' => $period->detectMissedPayments(),
        ];
    }

    /**
     * Lock the run. Loan balances and arrears are only touched here.
     */
    public function approve(PayrollPeriod $period): void
    {
        abort_unless($period->status === 'draft' && $period->entries()->exists(), 422, 'Generate the payroll before approving it.');

        DB::transaction(function () use ($period) {
            $items = $period->entries()
                ->where('status', 'included')
                ->with('items')
                ->get()
                ->flatMap->items;

            foreach ($items->where('reference_type', 'staff_deduction') as $item) {
                $deduction = StaffDeduction::find($item->reference_id);

                if ($deduction?->total_amount) {
                    $deduction->increment('amount_recovered', $item->amount);

                    if ((float) $deduction->fresh()->amount_recovered >= (float) $deduction->total_amount) {
                        $deduction->update(['is_active' => false]);
                    }
                }
            }

            SalaryArrear::whereKey($items->where('reference_type', 'salary_arrear')->pluck('reference_id'))
                ->update(['status' => 'paid', 'applied_in_period_id' => $period->getKey()]);

            $period->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        });
    }

    public function markPaid(PayrollPeriod $period, string $date, string $method, ?string $reference = null): void
    {
        abort_unless($period->status === 'approved', 422, 'Approve the payroll before marking it paid.');

        $period->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_date' => $date,
            'payment_method' => $method,
            'payment_reference' => $reference,
        ]);
    }

    /**
     * Add up the run's included payslips into the period totals.
     */
    public function refreshTotals(PayrollPeriod $period): void
    {
        $sum = fn (string $column) => (float) $period->entries()->where('status', 'included')->sum($column);

        $period->update([
            'staff_count' => $period->entries()->where('status', 'included')->count(),
            'total_gross' => $sum('gross_pay'),
            'total_allowances' => $sum('total_allowances'),
            'total_deductions' => $sum('total_deductions'),
            'total_statutory' => $sum('total_statutory'),
            'total_paye' => $sum('paye'),
            'total_nssf_employee' => $sum('nssf_employee'),
            'total_lst' => $sum('lst'),
            'total_employer_nssf' => $sum('nssf_employer'),
            'total_net' => $sum('net_pay'),
        ]);
    }

    protected function countryCode(?string $country): string
    {
        return match (strtolower((string) $country)) {
            'kenya' => 'KE',
            'tanzania' => 'TZ',
            'rwanda' => 'RW',
            'south sudan' => 'SS',
            'burundi' => 'BI',
            default => 'UG',
        };
    }
}
