<?php

namespace App\Services\Finance;

use App\Models\BudgetLine;
use App\Models\FinanceCategory;
use App\Models\FinanceEntry;
use App\Models\PayrollPeriod;
use App\Models\StudentPayment;
use App\Models\Term;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Income against expenditure for a period, category by category, with
 * the budget beside the actual figures.
 *
 * Two lines fill themselves:
 *   School fees       every fee receipt in the period (voided ones excluded)
 *   Salaries & wages  every approved or paid payroll run for a month in the
 *                     period: gross pay plus the school's 10% NSSF
 * Everything else comes from income and expense entries.
 */
class FinanceReport
{
    /**
     * @param  Collection<int, Term>|null  $budgetTerms  terms whose budgets apply; null = terms starting in the period
     * @return array{
     *     from: Carbon, to: Carbon,
     *     income: Collection<int, array>, expense: Collection<int, array>,
     *     totals: array{income: float, expense: float, balance: float, income_budget: float, expense_budget: float},
     *     months: Collection<int, array>
     * }
     */
    public function build(int $schoolId, CarbonInterface $from, CarbonInterface $to, ?Collection $budgetTerms = null): array
    {
        // Work in immutable dates whatever the caller passes.
        $from = CarbonImmutable::instance($from);
        $to = CarbonImmutable::instance($to);

        FinanceCategory::ensureDefaults($schoolId);

        $budgetTerms ??= Term::where('school_id', $schoolId)
            ->whereBetween('start_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        $budgets = BudgetLine::whereIn('term_id', $budgetTerms->pluck('id'))
            ->get()
            ->groupBy('finance_category_id')
            ->map(fn ($lines) => (float) $lines->sum('amount'));

        $entries = FinanceEntry::where('school_id', $schoolId)
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->get(['finance_category_id', 'amount', 'entry_date', 'type']);

        $fees = StudentPayment::where('school_id', $schoolId)
            ->whereBetween('paid_on', [$from->toDateString(), $to->toDateString()])
            ->get(['amount', 'paid_on']);

        $payroll = $this->payrollRuns($schoolId, $from, $to);

        $categories = FinanceCategory::where('school_id', $schoolId)->orderBy('sort_order')->orderBy('name')->get();

        $rows = $categories->map(function (FinanceCategory $category) use ($entries, $fees, $payroll, $budgets) {
            $actual = match ($category->system_key) {
                'fees' => (float) $fees->sum('amount'),
                'payroll' => (float) $payroll->sum('cost'),
                default => (float) $entries->where('finance_category_id', $category->id)->sum('amount'),
            };
            $budget = (float) ($budgets[$category->id] ?? 0);

            return [
                'category' => $category,
                'budget' => $budget,
                'actual' => $actual,
                'variance' => $actual - $budget,
                'percent' => $budget > 0 ? round($actual / $budget * 100) : null,
            ];
        })->filter(fn ($r) => $r['category']->is_active || $r['actual'] || $r['budget']);

        $income = $rows->where('category.type', 'income')->values();
        $expense = $rows->where('category.type', 'expense')->values();

        return [
            'from' => $from,
            'to' => $to,
            'income' => $income,
            'expense' => $expense,
            'totals' => [
                'income' => (float) $income->sum('actual'),
                'expense' => (float) $expense->sum('actual'),
                'balance' => (float) $income->sum('actual') - (float) $expense->sum('actual'),
                'income_budget' => (float) $income->sum('budget'),
                'expense_budget' => (float) $expense->sum('budget'),
            ],
            'months' => $this->months($from, $to, $entries, $fees, $payroll),
        ];
    }

    /**
     * Payroll runs for months inside the period, with their cost to the
     * school (gross + employer NSSF). Drafts are not money spent yet.
     *
     * @return Collection<int, array{month: Carbon, cost: float}>
     */
    protected function payrollRuns(int $schoolId, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return PayrollPeriod::where('school_id', $schoolId)
            ->whereIn('status', ['approved', 'paid'])
            ->get()
            ->map(fn (PayrollPeriod $p) => [
                'month' => CarbonImmutable::create($p->year, $p->month, 1),
                'cost' => (float) $p->total_gross + (float) $p->total_employer_nssf,
            ])
            ->filter(fn ($p) => $p['month']->between($from->startOfMonth(), $to))
            ->values();
    }

    /**
     * @return Collection<int, array{month: Carbon, income: float, expense: float}>
     */
    protected function months(CarbonImmutable $from, CarbonImmutable $to, Collection $entries, Collection $fees, Collection $payroll): Collection
    {
        $months = collect();
        $cursor = $from->startOfMonth();

        while ($cursor <= $to && $months->count() < 24) {
            $key = $cursor->format('Y-m');
            $inMonth = fn ($date) => Carbon::parse($date)->format('Y-m') === $key;

            $months->push([
                'month' => $cursor,
                'income' => (float) $fees->filter(fn ($p) => $inMonth($p->paid_on))->sum('amount')
                    + (float) $entries->where('type', 'income')->filter(fn ($e) => $inMonth($e->entry_date))->sum('amount'),
                'expense' => (float) $entries->where('type', 'expense')->filter(fn ($e) => $inMonth($e->entry_date))->sum('amount')
                    + (float) $payroll->filter(fn ($p) => $p['month']->format('Y-m') === $key)->sum('cost'),
            ]);

            $cursor = $cursor->addMonth(); // immutable: keep the result
        }

        return $months;
    }
}
