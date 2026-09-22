<?php

namespace App\Filament\Widgets;

use App\Models\School;
use App\Models\SubscriptionPayment;
use App\Services\Subscriptions\SubscriptionManager;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Platform owner's view of subscriptions across all schools.
 */
class SubscriptionOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return filament()->getCurrentPanel()?->getId() === 'admin'
            && (auth()->user()?->hasRole('Super Admin') ?? false);
    }

    protected function getStats(): array
    {
        $states = School::all()->map(fn (School $s) => SubscriptionManager::status($s));
        $count = fn (string ...$st) => $states->whereIn('state', $st)->count();
        $expiring = $states->filter(fn ($s) => $s['expiring'] || $s['state'] === 'grace')->count();

        $thisYear = SubscriptionPayment::whereYear('paid_on', now()->year)->sum('amount');
        $thisMonth = SubscriptionPayment::whereYear('paid_on', now()->year)->whereMonth('paid_on', now()->month)->sum('amount');

        return [
            Stat::make('Paying schools', $count('active'))
                ->description($count('trial') . ' on trial')
                ->color('success'),
            Stat::make('Need attention', $expiring)
                ->description('ending within ' . config('subscriptions.warn_days') . ' days or overdue')
                ->color($expiring ? 'warning' : 'gray'),
            Stat::make('Locked', $count('expired', 'none', 'suspended'))
                ->description('expired, no plan or suspended')
                ->color($count('expired', 'none', 'suspended') ? 'danger' : 'gray'),
            Stat::make('Received this year', 'UGX ' . number_format($thisYear))
                ->description('UGX ' . number_format($thisMonth) . ' this month'),
        ];
    }
}
