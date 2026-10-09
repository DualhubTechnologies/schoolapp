<?php

namespace App\Filament\App\Widgets;

use App\Filament\Admin\Resources\DemoRequests\DemoRequestResource;
use App\Filament\Admin\Resources\Plans\PlanResource;
use App\Filament\Admin\Resources\Schools\SchoolResource;
use App\Filament\App\Resources\Expenses\ExpenseResource;
use App\Filament\App\Resources\FeeBalances\FeeBalanceResource;
use App\Filament\App\Resources\FeeReminders\FeeReminderResource;
use App\Filament\App\Resources\PayrollPeriods\PayrollPeriodResource;
use App\Filament\App\Resources\SalaryArrears\SalaryArrearResource;
use App\Filament\App\Resources\Staff\StaffResource;
use App\Filament\App\Resources\Students\StudentResource;
use App\Filament\Pages\ClassResults;
use App\Filament\Pages\EnterMarks;
use App\Filament\Pages\MarksProgress;
use App\Filament\Pages\ReceivePayment;
use App\Filament\Pages\ReportCards;
use App\Filament\Pages\SendMessages;
use App\Filament\Pages\StudentIdCards;
use App\Filament\Pages\TakeAttendance;
use App\Filament\Widgets\Concerns\SchoolScoped;
use App\Support\AcademicAccess;
use App\Support\DashboardProfile;
use App\Support\Modules;
use Filament\Widgets\Widget;

/**
 * Top of every dashboard: who you are, where the term stands, and the
 * two or three things your role does most, one click away.
 */
class WelcomeBanner extends Widget
{
    use SchoolScoped;

    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.app.widgets.welcome-banner';

    protected static bool $isLazy = false;

    public function greeting(): string
    {
        $hour = (int) now()->timezone(config('app.timezone'))->format('G');

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    public function firstName(): string
    {
        $name = trim((string) auth()->user()?->name);

        return $name === '' ? 'there' : str(explode(' ', $name)[0])->lower()->ucfirst()->toString();
    }

    /** @return array{label: string, week: int, weeks: int, percent: int, daysLeft: int}|null */
    public function termProgress(): ?array
    {
        $term = $this->currentTerm();

        if (! $term?->start_date || ! $term?->end_date || $term->end_date->lte($term->start_date)) {
            return $term ? ['label' => $term->label(), 'week' => 0, 'weeks' => 0, 'percent' => 0, 'daysLeft' => 0] : null;
        }

        $total = $term->start_date->diffInDays($term->end_date) + 1;
        $elapsed = (int) min($total, max(0, $term->start_date->diffInDays(today(), false) + 1));

        return [
            'label' => $term->label(),
            'week' => (int) max(1, ceil($elapsed / 7)),
            'weeks' => (int) ceil($total / 7),
            'percent' => (int) round($elapsed / $total * 100),
            'daysLeft' => (int) max(0, today()->diffInDays($term->end_date, false)),
        ];
    }

    /** @return list<array{label: string, url: string, icon: string, primary?: bool}> */
    public function actions(): array
    {
        $candidates = match (DashboardProfile::for()) {
            DashboardProfile::LEADERSHIP => [
                [ReceivePayment::class, 'Receive payment', 'heroicon-o-banknotes', true],
                [StudentResource::class, 'Add student', 'heroicon-o-user-plus', false, 'create'],
                [EnterMarks::class, 'Enter marks', 'heroicon-o-pencil-square'],
                [ReportCards::class, 'Report cards', 'heroicon-o-document-text'],
            ],
            DashboardProfile::BURSAR => [
                [ReceivePayment::class, 'Receive payment', 'heroicon-o-banknotes', true],
                [FeeBalanceResource::class, 'Fee balances', 'heroicon-o-scale'],
                [FeeReminderResource::class, 'Reminder letters', 'heroicon-o-envelope'],
                [ExpenseResource::class, 'Record expense', 'heroicon-o-receipt-percent', false, 'create'],
            ],
            DashboardProfile::HR => [
                [PayrollPeriodResource::class, 'Run payroll', 'heroicon-o-calculator', true],
                [StaffResource::class, 'Staff', 'heroicon-o-identification'],
                [SalaryArrearResource::class, 'Salary arrears', 'heroicon-o-exclamation-circle'],
            ],
            DashboardProfile::TEACHER => [
                [EnterMarks::class, 'Enter marks', 'heroicon-o-pencil-square', true],
                [MarksProgress::class, 'Marks progress', 'heroicon-o-chart-pie'],
                [TakeAttendance::class, 'Take register', 'heroicon-o-clipboard-document-check'],
                [ClassResults::class, 'Class results', 'heroicon-o-chart-bar'],
                [ReportCards::class, 'Report cards', 'heroicon-o-document-text'],
            ],
            default => [
                [StudentResource::class, 'Admit a learner', 'heroicon-o-user-plus', true, 'create'],
                [TakeAttendance::class, 'Take register', 'heroicon-o-clipboard-document-check'],
                [SendMessages::class, 'Send a message', 'heroicon-o-chat-bubble-left-right'],
                [StudentIdCards::class, 'ID cards', 'heroicon-o-identification'],
            ],
        };

        // The platform owner's shortcuts live in the admin panel.
        if (DashboardProfile::for() === DashboardProfile::PLATFORM) {
            return [
                ['url' => SchoolResource::getUrl(panel: 'admin'), 'label' => 'Manage schools', 'icon' => 'heroicon-o-building-office-2', 'primary' => true],
                ['url' => DemoRequestResource::getUrl(panel: 'admin'), 'label' => 'Demo requests', 'icon' => 'heroicon-o-calendar-days', 'primary' => false],
                ['url' => PlanResource::getUrl(panel: 'admin'), 'label' => 'Plans & pricing', 'icon' => 'heroicon-o-rectangle-stack', 'primary' => false],
            ];
        }

        return collect($candidates)
            ->filter(fn ($a) => $this->mayOpen($a[0], $a[4] ?? null))
            ->map(fn ($a) => [
                // Some resources add records in a pop-up on their list page: open it straight away.
                'url' => match (true) {
                    ! isset($a[4]) => $a[0]::getUrl(),
                    $a[0]::hasPage($a[4]) => $a[0]::getUrl($a[4]),
                    default => $a[0]::getUrl(parameters: ['action' => $a[4]]),
                },
                'label' => $a[1],
                'icon' => $a[2],
                'primary' => $a[3] ?? false,
            ])
            ->values()
            ->all();
    }

    /** Pages and resources each say who may open them. */
    protected function mayOpen(string $class, ?string $action = null): bool
    {
        // An "add" shortcut needs the right to add, not just to look.
        if ($action === 'create' && method_exists($class, 'canCreate') && ! $class::canCreate()) {
            return false;
        }

        // Report cards are written by class teachers (and printed by the school).
        if ($class === ReportCards::class && ! AcademicAccess::manages() && AcademicAccess::classTeacherClassIds() === []) {
            return false;
        }

        return method_exists($class, 'canAccess') ? $class::canAccess() : $class::canViewAny();
    }

    public function profileLabel(): string
    {
        // The admissions office's own badge, rather than a plain "Welcome".
        if (DashboardProfile::for() === DashboardProfile::BASIC && Modules::allows('admissions')) {
            return 'Admissions & records';
        }

        // A Director of Studies runs the exams rather than one class's teaching.
        if (DashboardProfile::for() === DashboardProfile::TEACHER && AcademicAccess::manages()) {
            return 'Exams & studies';
        }

        return DashboardProfile::LABELS[DashboardProfile::for()];
    }
}
