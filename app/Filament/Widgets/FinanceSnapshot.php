<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\IncomeExpenditure;
use App\Models\Term;
use App\Services\Finance\FinanceReport;
use App\Support\FinanceAccess;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * This term's money at a glance: received, spent, and what is left.
 */
class FinanceSnapshot extends StatsOverviewWidget
{
    /** Loads with the dashboard, so it never shows as an empty box. */
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected ?string $heading = 'This term: received vs spent';

    public static function canView(): bool
    {
        return FinanceAccess::allowed();
    }

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $term = Term::current(auth()->user()->school_id);

        if (! $term?->start_date || ! $term?->end_date) {
            return [Stat::make('Finance', '—')->description('Set the current term\'s dates to see income and spending.')];
        }

        $r = app(FinanceReport::class)->build(auth()->user()->school_id, $term->start_date->copy(), $term->end_date->copy(), collect([$term]));
        $t = $r['totals'];
        $link = IncomeExpenditure::getUrl();

        return [
            Stat::make('Received', 'UGX '.number_format($t['income']))
                ->description($t['income_budget'] > 0 ? round($t['income'] / $t['income_budget'] * 100).'% of budgeted income' : 'Fees + other income')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url($link),
            Stat::make('Spent', 'UGX '.number_format($t['expense']))
                ->description($t['expense_budget'] > 0 ? round($t['expense'] / $t['expense_budget'] * 100).'% of budgeted spending' : 'Expenses + salaries')
                ->icon('heroicon-o-arrow-up-tray')
                ->color($t['expense_budget'] > 0 && $t['expense'] > $t['expense_budget'] ? 'danger' : 'warning')
                ->url($link),
            Stat::make($t['balance'] >= 0 ? 'Surplus' : 'Deficit', 'UGX '.number_format(abs($t['balance'])))
                ->description($term->label())
                ->icon('heroicon-o-scale')
                ->color($t['balance'] >= 0 ? 'success' : 'danger')
                ->url($link),
        ];
    }
}
