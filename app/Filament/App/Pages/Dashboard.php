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
use App\Filament\App\Widgets\WorkGuide;
use App\Filament\Widgets\AdmissionsTrendChart;
use App\Filament\Widgets\FeeCollectionChart;
use App\Filament\Widgets\FinanceSnapshot;
use App\Filament\Widgets\OutstandingByClassChart;
use App\Filament\Widgets\PaymentMethodsChart;
use App\Filament\Widgets\RecentPaymentsTable;
use App\Filament\Widgets\SetupChecklist;
use App\Filament\Widgets\StudentsByClassChart;
use App\Filament\Widgets\TermBillingPrompt;
use App\Filament\Widgets\TopDebtorsTable;
use App\Models\School;
use App\Services\SchoolStarterSetup;
use App\Support\AcademicAccess;
use App\Support\DashboardProfile;
use App\Support\SchoolType;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

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

    /**
     * A newly registered school's administrator is offered the recommended
     * setup the first time they reach the dashboard.
     */
    public function mount(): void
    {
        if (SchoolStarterSetup::isOfferedTo(auth()->user())) {
            $this->defaultAction = 'schoolSetup';
        }
    }

    /**
     * "Set up your school": fill in the standard Ugandan structure, or skip
     * and build it by hand. Either answer is final -- the offer does not
     * come back -- and everything it adds stays editable.
     */
    public function schoolSetupAction(): Action
    {
        return Action::make('schoolSetup')
            ->visible(fn (): bool => SchoolStarterSetup::isOfferedTo(auth()->user()))
            ->modalIcon(Heroicon::OutlinedSparkles)
            ->modalHeading(fn (): string => 'Welcome! Let\'s set up '.$this->school()?->name)
            ->modalDescription('We can fill in the standard Ugandan school structure for you, so you can start adding learners and fees today instead of building everything from scratch.')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalCloseButton(false)
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->modalCancelAction(false)
            ->modalSubmitActionLabel('Use recommended setup')
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('skip', ['skip' => true])
                    ->label('Skip, I\'ll set up later')
                    ->color('gray'),
            ])
            ->fillForm(fn (): array => [
                'sections' => SchoolType::keys($this->school()),
                'boarding_type' => $this->school()?->boarding_type ?: 'day',
            ])
            ->schema([
                Grid::make(['default' => 1, 'md' => 2])
                    ->schema([
                        CheckboxList::make('sections')
                            ->label('Sections your school runs')
                            ->options(fn (): array => SchoolType::curricula($this->school()))
                            ->descriptions(SchoolStarterSetup::SECTION_HINTS)
                            ->columns(2),
                        Radio::make('boarding_type')
                            ->label('Your learners are')
                            ->options([
                                'day' => 'Day scholars',
                                'boarding' => 'Boarders',
                                'mixed' => 'Both day and boarding',
                            ])
                            ->required(),
                    ]),
                View::make('filament.app.setup.whats-included')
                    ->viewData(fn (): array => [
                        'terms' => $terms = SchoolStarterSetup::termsFor(),
                        'currentTerm' => $terms[SchoolStarterSetup::currentTermIndex($terms)]['name'],
                        'officialCalendar' => SchoolStarterSetup::hasOfficialCalendar(),
                        'year' => today()->year,
                        'isPrimary' => SchoolType::isPrimary($this->school()),
                    ]),
                Callout::make('Nothing here is locked in')
                    ->description('Every class, term, subject and grade band is an ordinary record. Rename, edit or delete any of them later on its own page. Streams are optional: each class works on its own, so only add streams if you split a class.')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->info(),
            ])
            ->action(function (Action $action, array $data, array $arguments, SchoolStarterSetup $setup): void {
                $school = $this->school();

                if ($arguments['skip'] ?? false) {
                    $setup->skip($school);

                    Notification::make()
                        ->title('No problem, you can set up at your own pace')
                        ->body('The checklist on your dashboard shows each step: your academic year, classes, fees and learners.')
                        ->info()
                        ->send();

                    $this->redirect(static::getUrl());

                    return;
                }

                if (empty($data['sections'])) {
                    Notification::make()
                        ->title('Tick at least one section')
                        ->body('Choose the sections your school runs, or skip to set up by hand.')
                        ->warning()
                        ->send();

                    $action->halt();
                }

                $result = $setup->run($school, $data['sections'], $data['boarding_type']);

                Notification::make()
                    ->title('Your school is ready')
                    ->body(sprintf(
                        'Added %d classes, the %s academic year with %d terms, %d subjects and %d grading scales%s. Next, set your fees and add your learners.',
                        $result['classes'],
                        $result['year'],
                        $result['terms'],
                        $result['subjects'],
                        $result['scales'],
                        $result['combinations'] ? " and {$result['combinations']} A-Level combinations" : '',
                    ))
                    ->success()
                    ->persistent()
                    ->send();

                $this->redirect(static::getUrl());
            });
    }

    protected function school(): ?School
    {
        return auth()->user()?->school;
    }

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
                WorkGuide::class,
                SetupChecklist::class,
                TermBillingPrompt::class,
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
                WorkGuide::class,
                TermBillingPrompt::class,
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
                WorkGuide::class,
                HrKpis::class,
            ],
            // A Director of Studies who teaches nothing has no "my subjects".
            DashboardProfile::TEACHER => AcademicAccess::manages() && ! AcademicAccess::hasTeachingLoad()
                ? [WelcomeBanner::class, WorkGuide::class]
                : [WelcomeBanner::class, WorkGuide::class, TeacherKpis::class, TeacherMarksProgress::class],
            default => [
                WelcomeBanner::class,
                WorkGuide::class,
            ],
        };
    }
}
