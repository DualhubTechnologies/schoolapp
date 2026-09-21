<?php

namespace App\Filament\App\Resources\Payments\Widgets;

use App\Models\StudentPayment;
use App\Models\Term;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * End-of-day figures for the receipt book: what came in today (and how),
 * this week, and this term.
 */
class CollectionsSummary extends StatsOverviewWidget
{
    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $base = fn () => StudentPayment::where('school_id', auth()->user()?->school_id);

        $today = $base()->whereDate('paid_on', today());
        $todayTotal = (float) (clone $today)->sum('amount');
        $todayCount = (clone $today)->count();

        $byMethod = (clone $today)
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method')
            ->map(fn ($v, $m) => (StudentPayment::METHODS[$m] ?? $m) . ' ' . number_format((float) $v))
            ->implode(' · ');

        $week = (float) $base()->whereBetween('paid_on', [now()->startOfWeek(), now()->endOfWeek()])->sum('amount');

        $term = Term::current();
        $termTotal = $term
            ? (float) $base()->where(fn ($q) => $q->where('term_id', $term->getKey())
                ->when($term->start_date && $term->end_date, fn ($q) => $q->orWhere(fn ($q) => $q
                    ->whereNull('term_id')->whereBetween('paid_on', [$term->start_date, $term->end_date]))))
                ->sum('amount')
            : 0.0;

        return [
            Stat::make('Collected today', 'UGX ' . number_format($todayTotal))
                ->description($todayCount . ' ' . str('receipt')->plural($todayCount))
                ->icon('heroicon-o-banknotes')
                ->color('success'),
            Stat::make('Today by method', $byMethod ?: '—')
                ->description('Match against cash in hand and statements')
                ->icon('heroicon-o-scale'),
            Stat::make('This week', 'UGX ' . number_format($week))
                ->description(now()->startOfWeek()->format('j M') . ' – ' . now()->endOfWeek()->format('j M'))
                ->icon('heroicon-o-calendar-days'),
            Stat::make('This term', 'UGX ' . number_format($termTotal))
                ->description($term?->label() ?? 'No current term')
                ->icon('heroicon-o-academic-cap')
                ->color('primary'),
        ];
    }
}
