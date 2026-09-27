<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Widgets\BursarKpis;
use App\Filament\App\Widgets\HrKpis;
use App\Filament\App\Widgets\LeadershipKpis;
use App\Filament\App\Widgets\PlatformActivityKpis;
use App\Filament\App\Widgets\PlatformKpis;
use App\Filament\App\Widgets\TeacherKpis;
use App\Filament\App\Widgets\TeacherMarksProgress;
use App\Filament\App\Widgets\WelcomeBanner;
use App\Filament\Widgets\AdmissionsTrendChart;
use App\Filament\Widgets\FeeCollectionChart;
use App\Filament\Widgets\FinanceSnapshot;
use App\Filament\Widgets\OutstandingByClassChart;
use App\Filament\Widgets\PaymentMethodsChart;
use App\Filament\Widgets\RecentPaymentsTable;
use App\Filament\Widgets\SetupChecklist;
use App\Filament\Widgets\StudentsByClassChart;
use App\Filament\Widgets\TopDebtorsTable;
use App\Support\DashboardProfile;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * The school dashboard, at /dashboard so the site root can be the public
 * landing page. Kept outside app/Filament/Pages so the admin panel, which
 * discovers that folder, keeps its own dashboard.
 *
 * Each user group gets its own dashboard (see DashboardProfile); the order
 * listed here is the order on the page. Every widget still checks its own
 * canView(), so nobody sees figures their modules do not allow.
 */
class Dashboard extends BaseDashboard
{
    protected static string $routePath = '/dashboard';

    public function getWidgets(): array
    {
        return match (DashboardProfile::for()) {
            DashboardProfile::PLATFORM => [
                WelcomeBanner::class,
                PlatformKpis::class,
                PlatformActivityKpis::class,
            ],
            DashboardProfile::LEADERSHIP => [
                WelcomeBanner::class,
                SetupChecklist::class,
                LeadershipKpis::class,
                FeeCollectionChart::class,
                StudentsByClassChart::class,
                AdmissionsTrendChart::class,
                PaymentMethodsChart::class,
                OutstandingByClassChart::class,
                FinanceSnapshot::class,
                TopDebtorsTable::class,
                RecentPaymentsTable::class,
            ],
            DashboardProfile::BURSAR => [
                WelcomeBanner::class,
                BursarKpis::class,
                FeeCollectionChart::class,
                PaymentMethodsChart::class,
                OutstandingByClassChart::class,
                StudentsByClassChart::class,
                FinanceSnapshot::class,
                RecentPaymentsTable::class,
                TopDebtorsTable::class,
            ],
            DashboardProfile::HR => [
                WelcomeBanner::class,
                HrKpis::class,
            ],
            DashboardProfile::TEACHER => [
                WelcomeBanner::class,
                TeacherKpis::class,
                TeacherMarksProgress::class,
            ],
            default => [
                WelcomeBanner::class,
            ],
        };
    }
}
