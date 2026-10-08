<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Resources\Assessments\AssessmentResource;
use App\Filament\App\Resources\Expenses\ExpenseResource;
use App\Filament\App\Resources\FeeBalances\FeeBalanceResource;
use App\Filament\App\Resources\FeeReminders\FeeReminderResource;
use App\Filament\App\Resources\FeeStructures\FeeStructureResource;
use App\Filament\App\Resources\PayrollPeriods\PayrollPeriodResource;
use App\Filament\App\Resources\Staff\StaffResource;
use App\Filament\App\Resources\Students\StudentResource;
use App\Filament\App\Resources\Users\UserResource;
use App\Filament\Pages\AttendanceReport;
use App\Filament\Pages\ClassResults;
use App\Filament\Pages\EnterMarks;
use App\Filament\Pages\MarksProgress;
use App\Filament\Pages\PromoteStudents;
use App\Filament\Pages\ReceivePayment;
use App\Filament\Pages\ReportCards;
use App\Filament\Pages\SendMessages;
use App\Filament\Pages\StaffIdCards;
use App\Filament\Pages\StartTermBilling;
use App\Filament\Pages\StudentIdCards;
use App\Filament\Pages\TakeAttendance;
use App\Filament\Widgets\Concerns\SchoolScoped;
use App\Models\AttendanceRecord;
use App\Models\PayrollPeriod;
use App\Models\Term;
use App\Services\BillingService;
use App\Support\AcademicAccess;
use App\Support\DashboardProfile;
use App\Support\Modules;
use Filament\Widgets\Widget;

/**
 * "Your work, step by step": for each job the signed-in user does, the
 * steps in the order they are done, in plain words, each one click away.
 * Built from what the user may open, so a bursar sees fees, a class
 * teacher the register and marks, a secretary admissions, and someone
 * with several jobs sees each of them. New users see it open; anyone can
 * hide it and bring it back.
 */
class WorkGuide extends Widget
{
    use SchoolScoped;

    protected static ?int $sort = -5;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.app.widgets.work-guide';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return filled(auth()->user()?->school_id) && ! DashboardProfile::is(DashboardProfile::PLATFORM);
    }

    public function hidden(): bool
    {
        return auth()->user()?->tips_hidden_at !== null;
    }

    /** A user in their first month gets a warmer welcome. */
    public function isNewUser(): bool
    {
        return auth()->user()?->created_at?->gt(now()->subDays(30)) ?? false;
    }

    public function hideTips(): void
    {
        auth()->user()?->forceFill(['tips_hidden_at' => now()])->save();
    }

    public function showTips(): void
    {
        auth()->user()?->forceFill(['tips_hidden_at' => null])->save();
    }

    /**
     * @return array<int, array{key: string, title: string, icon: string, steps: array<int, mixed>}>
     */
    public function jobs(): array
    {
        return once(fn () => collect($this->candidateJobs())
            ->map(function (array $job) {
                $job['steps'] = collect($job['steps'])
                    ->filter(fn (array $step) => $this->mayOpen($step['page'], $step['action'] ?? null))
                    ->map(fn (array $step) => [
                        'title' => $step['title'],
                        'text' => $step['text'],
                        'url' => $this->urlFor($step['page'], $step['action'] ?? null),
                        'done' => isset($step['done']) ? ($step['done'])() : null,
                    ])
                    ->values()
                    ->all();

                return $job;
            })
            ->filter(fn (array $job) => $job['steps'] !== [])
            ->sortBy(fn (array $job) => array_search($job['key'], $this->jobOrder(), true))
            ->values()
            ->all());
    }

    /**
     * @return list<array{key: string, title: string, icon: string, steps: list<array{page: string, title: string, text: string, action?: string, done?: (\Closure(): ?bool)|null}>}>
     */
    protected function candidateJobs(): array
    {
        $manages = AcademicAccess::manages();
        $isClassTeacher = AcademicAccess::classTeacherClassIds() !== [];

        return [
            [
                'key' => 'admissions',
                'title' => 'Admissions',
                'icon' => 'heroicon-o-user-plus',
                'steps' => [
                    ['page' => StudentResource::class, 'action' => 'create', 'title' => 'Admit a new learner',
                        'text' => 'Fill in the learner\'s name, class and a parent\'s phone number. The registration number is given automatically.'],
                    ['page' => StudentResource::class, 'title' => 'Find or update a learner',
                        'text' => 'Search by name or admission number to correct details or change a class.'],
                ],
            ],
            [
                'key' => 'id_cards',
                'title' => 'ID cards',
                'icon' => 'heroicon-o-identification',
                'steps' => [
                    ['page' => StudentIdCards::class, 'title' => 'Print learners\' ID cards',
                        'text' => 'Choose a class and print its learners\' cards.'],
                    ['page' => StaffIdCards::class, 'title' => 'Print staff ID cards',
                        'text' => 'Cards for every staff member, with their photo.'],
                ],
            ],
            [
                'key' => 'fees',
                'title' => 'Fees',
                'icon' => 'heroicon-o-banknotes',
                'steps' => [
                    ['page' => FeeStructureResource::class, 'title' => 'Check this term\'s fees',
                        'text' => 'Make sure each class shows the right amounts before billing.'],
                    ['page' => StartTermBilling::class, 'title' => 'Bill the term',
                        'text' => 'One click puts this term\'s fees on every learner\'s account.',
                        'done' => fn () => $this->termBilled()],
                    ['page' => ReceivePayment::class, 'title' => 'Receive a payment',
                        'text' => 'Find the learner, type the amount, and print the receipt for the parent.'],
                    ['page' => FeeBalanceResource::class, 'title' => 'Follow up balances',
                        'text' => 'See who still owes, and how much.'],
                    ['page' => FeeReminderResource::class, 'title' => 'Send reminders',
                        'text' => 'Print reminder letters or text parents who owe.'],
                ],
            ],
            [
                'key' => 'spending',
                'title' => 'Spending',
                'icon' => 'heroicon-o-receipt-percent',
                'steps' => [
                    ['page' => ExpenseResource::class, 'action' => 'create', 'title' => 'Record an expense',
                        'text' => 'Every shilling spent, with what it was for, so the books balance.'],
                ],
            ],
            [
                'key' => 'staff',
                'title' => 'Staff & salaries',
                'icon' => 'heroicon-o-identification',
                'steps' => [
                    ['page' => StaffResource::class, 'action' => 'create', 'title' => 'Add a staff member',
                        'text' => 'Name, job and monthly salary. The staff number is given automatically.'],
                    ['page' => PayrollPeriodResource::class, 'title' => 'Start this month\'s payroll',
                        'text' => 'SchoolHub works out PAYE, NSSF and each person\'s pay. Check it, approve it, then mark it paid.',
                        'done' => fn () => $this->payrollStarted()],
                ],
            ],
            [
                'key' => 'class',
                'title' => $isClassTeacher && ! $manages ? 'My class' : 'Attendance',
                'icon' => 'heroicon-o-clipboard-document-check',
                'steps' => [
                    ['page' => TakeAttendance::class, 'title' => 'Take today\'s register',
                        'text' => 'Everyone is marked present; tap only those absent or late, then save. Parents of absent learners can be texted.',
                        'done' => $isClassTeacher && ! Modules::hasFullAccess() ? fn () => $this->registerTakenToday() : null],
                    ['page' => AttendanceReport::class, 'title' => 'See attendance',
                        'text' => 'Who has missed school this term, by class.'],
                ],
            ],
            [
                'key' => 'marks',
                'title' => 'Marks & reports',
                'icon' => 'heroicon-o-pencil-square',
                'steps' => [
                    ...($manages ? [['page' => AssessmentResource::class, 'title' => 'Set up the term\'s exams',
                        'text' => 'Beginning of term, mid-term, end of term — and any tests (CA) for one class or subject.']] : []),
                    ['page' => EnterMarks::class, 'title' => $manages ? 'Enter marks' : 'Enter marks for my subjects',
                        'text' => 'Choose the exam, class and subject, type each learner\'s mark. It saves as you go.'],
                    ...($manages ? [['page' => MarksProgress::class, 'title' => 'Check which marks are missing',
                        'text' => 'See which teachers still have marks to enter.']] : []),
                    ['page' => ClassResults::class, 'title' => 'See class results',
                        'text' => 'Positions, averages and grades for a class.'],
                    ...($manages || $isClassTeacher ? [['page' => ReportCards::class, 'title' => $manages ? 'Comments and report cards' : 'Write comments and print report cards',
                        'text' => 'Write a comment for each learner, then print the report cards.']] : []),
                ],
            ],
            [
                'key' => 'messages',
                'title' => 'Messages',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'steps' => [
                    ['page' => SendMessages::class, 'title' => 'Text parents or staff',
                        'text' => 'Send one message to all parents, a class, parents who owe fees, or staff.'],
                ],
            ],
            [
                'key' => 'year_end',
                'title' => 'End of year',
                'icon' => 'heroicon-o-arrow-trending-up',
                'steps' => [
                    ['page' => PromoteStudents::class, 'title' => 'Promote learners',
                        'text' => 'Move each class up a year, and choose who repeats.'],
                ],
            ],
            [
                'key' => 'team',
                'title' => 'Your team',
                'icon' => 'heroicon-o-users',
                'steps' => [
                    ['page' => UserResource::class, 'action' => 'create', 'title' => 'Give a staff member a login',
                        'text' => 'Choose their job (teacher, bursar…) and they see only their own work. Share the password with them.'],
                ],
            ],
        ];
    }

    protected function termBilled(): ?bool
    {
        $term = Term::current($this->schoolId());

        return $term ? ! app(BillingService::class)->termNeedsBilling($term) : null;
    }

    protected function payrollStarted(): bool
    {
        return PayrollPeriod::where('school_id', $this->schoolId())
            ->where('year', today()->year)
            ->where('month', today()->month)
            ->exists();
    }

    protected function registerTakenToday(): bool
    {
        return AttendanceRecord::where('school_id', $this->schoolId())
            ->whereIn('school_class_id', AcademicAccess::classTeacherClassIds())
            ->whereDate('date', today())
            ->exists();
    }

    /** A page's URL, or a resource's list, or its $action (e.g. "create") page or pop-up. */
    protected function urlFor(string $class, ?string $action): string
    {
        if ($action === null) {
            return $class::getUrl();
        }

        // Resources that add records in a pop-up on their list page open it straight away.
        return $class::hasPage($action) ? $class::getUrl($action) : $class::getUrl(parameters: ['action' => $action]);
    }

    /**
     * The user's main job first: a teacher sees marks and their class
     * before admissions, a bursar sees fees first.
     *
     * @return list<string>
     */
    protected function jobOrder(): array
    {
        return match (DashboardProfile::for()) {
            DashboardProfile::TEACHER => AcademicAccess::manages()
                ? ['marks', 'class', 'admissions', 'id_cards', 'messages', 'fees', 'spending', 'staff', 'year_end', 'team']
                : ['class', 'marks', 'admissions', 'id_cards', 'messages', 'fees', 'spending', 'staff', 'year_end', 'team'],
            DashboardProfile::BURSAR => ['fees', 'spending', 'staff', 'admissions', 'class', 'marks', 'id_cards', 'messages', 'year_end', 'team'],
            DashboardProfile::HR => ['staff', 'fees', 'spending', 'admissions', 'class', 'marks', 'id_cards', 'messages', 'year_end', 'team'],
            default => ['admissions', 'fees', 'class', 'marks', 'staff', 'spending', 'id_cards', 'messages', 'team', 'year_end'],
        };
    }

    /** Pages and resources each say who may open them (and, for "create", who may add). */
    protected function mayOpen(string $class, ?string $action = null): bool
    {
        if (method_exists($class, 'canAccess')) {
            return $class::canAccess();
        }

        return $class::canViewAny() && ($action !== 'create' || $class::canCreate());
    }
}
