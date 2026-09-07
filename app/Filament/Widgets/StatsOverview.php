<?php

namespace App\Filament\Widgets;

use App\Models\PayrollPeriod;
use App\Models\SalaryArrear;
use App\Models\Staff;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user?->hasRole('Super Admin');

        if ($isSuperAdmin) {
            return $this->getSuperAdminStats();
        }

        return $this->getSchoolAdminStats($user->school_id);
    }

    protected function getSuperAdminStats(): array
    {
        return [
            Stat::make('Total Schools', \App\Models\School::count())
                ->description('Registered on platform')
                ->icon('heroicon-o-building-office-2')
                ->color('primary'),
            Stat::make('Total Users', \App\Models\User::count())
                ->description('Across all schools')
                ->icon('heroicon-o-users')
                ->color('info'),
            Stat::make('Total Staff', Staff::count())
                ->description('Across all schools')
                ->icon('heroicon-o-user-group')
                ->color('success'),
        ];
    }

    protected function getSchoolAdminStats(?int $schoolId): array
    {
        $activeStaff = Staff::where('school_id', $schoolId)->where('status', 'active')->count();
        $totalStaff = Staff::where('school_id', $schoolId)->count();

        $latestPayroll = PayrollPeriod::where('school_id', $schoolId)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();

        $pendingArrears = SalaryArrear::where('school_id', $schoolId)
            ->where('status', 'pending')
            ->count();

        $payrollLabel = $latestPayroll ? $latestPayroll->period_label : 'No payroll yet';
        $payrollNet = $latestPayroll ? 'UGX ' . number_format($latestPayroll->total_net, 0) : 'UGX 0';

        return [
            Stat::make('Active Staff', $activeStaff)
                ->description("{$totalStaff} total ({$activeStaff} active)")
                ->icon('heroicon-o-user-group')
                ->color('success'),
            Stat::make('Latest Payroll', $payrollNet)
                ->description($payrollLabel . ' — ' . ($latestPayroll?->status ?? 'none'))
                ->icon('heroicon-o-banknotes')
                ->color($latestPayroll?->status === 'paid' ? 'success' : 'warning'),
            Stat::make('Pending Arrears', $pendingArrears)
                ->description($pendingArrears > 0 ? 'Needs review' : 'All clear')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($pendingArrears > 0 ? 'danger' : 'success'),
        ];
    }
}