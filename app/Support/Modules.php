<?php

namespace App\Support;

use App\Models\School;
use App\Models\User;

/**
 * Which parts of the system each user may open.
 *
 * The school administrator ticks modules per user (Settings → Users). A
 * user with nothing ticked gets their role's defaults. School Admins (and
 * Super Admins) always have everything.
 *
 * Every page and resource is mapped to a module below; the same check
 * hides it from the menu and blocks its URL.
 */
class Modules
{
    /** key => [label, what it covers] */
    public const LIST = [
        'students' => ['Students', 'Student records, guardians'],
        'promotion' => ['Year-end promotion', 'Promote, repeat or complete students'],
        'messages' => ['Messages (SMS)', 'Send SMS to parents of all learners, a class, those owing fees, or staff'],
        'attendance' => ['Attendance', 'Take the daily class register, attendance reports, text parents of absent learners'],
        'id_cards' => ['Identity cards', 'Student and staff ID cards, and the card template'],
        'fees' => ['Fees', 'Receive payments, receipts, billing, balances, reminders, fee set-up'],
        'transport' => ['Transport', 'Van routes and fares, learners on the van, route lists'],
        'finance' => ['Finance & budget', 'Expenses, other income, budget, income vs expenditure'],
        'exams' => ['Exams & results', 'Enter marks (own subjects), results, report cards'],
        'exams_all' => ['Marks for all subjects', 'Enter marks for every subject and manage exams (Director of Studies)'],
        'hr' => ['HR & payroll', 'Staff records, salaries, payroll, payslips'],
        'academics' => ['Academics set-up', 'Years, terms, classes, streams, subjects, grading'],
        'settings' => ['Settings', 'School profile, users & access, audit trail'],
    ];

    /** What each role opens until the administrator chooses otherwise. */
    public const ROLE_DEFAULTS = [
        'Teacher' => ['exams', 'students', 'attendance'],
        'Accountant' => ['fees', 'transport', 'finance', 'hr'],
        'Bursar' => ['fees', 'transport', 'finance'],
        'Staff' => [],
        'Parent' => [],
        'Student' => [],
    ];

    /** Modules only primary schools have: the school van is a primary thing. */
    public const PRIMARY_ONLY = ['transport'];

    /** Roles that always have every module. */
    public const FULL_ACCESS_ROLES = ['Super Admin', 'School Admin'];

    /**
     * Resource / page class (short name) => module.
     */
    public const CLASS_MAP = [
        // Students
        'StudentResource' => 'students',
        'GuardianResource' => 'students',
        'PromoteStudents' => 'promotion',
        // Attendance
        'TakeAttendance' => 'attendance',
        'AttendanceReport' => 'attendance',
        // Messages
        'SendMessages' => 'messages',
        // Identity cards
        'StudentIdCards' => 'id_cards',
        'StaffIdCards' => 'id_cards',
        // Fees
        'ReceivePayment' => 'fees',
        'PaymentResource' => 'fees',
        'SchoolPayTransactionResource' => 'fees',
        'FeeBalanceResource' => 'fees',
        'StudentAccount' => 'fees',
        'BillingResource' => 'fees',
        'FeeReminderResource' => 'fees',
        'StudentDiscountResource' => 'fees',
        'FeeStructureResource' => 'fees',
        // Transport
        'TransportRouteResource' => 'transport',
        'TransportLearnerResource' => 'transport',
        'TransportCollections' => 'transport',
        'FeeStructureSheet' => 'fees',
        // Finance
        'IncomeExpenditure' => 'finance',
        'ExpenseResource' => 'finance',
        'IncomeResource' => 'finance',
        'TermBudget' => 'finance',
        'FinanceCategoryResource' => 'finance',
        'BankAccountResource' => 'finance',
        'BankStatementLineResource' => 'finance',
        // Exams & results
        'AssessmentResource' => 'exams',
        'EnterMarks' => 'exams',
        'MarksProgress' => 'exams',
        'ClassResults' => 'exams',
        'ReportCards' => 'exams',
        'SyllabusTopicResource' => 'exams',
        'AssessTopics' => 'exams',
        // HR & payroll
        'StaffResource' => 'hr',
        'PayrollPeriodResource' => 'hr',
        'SalaryArrearResource' => 'hr',
        'AllowanceTypeResource' => 'hr',
        'DeductionTypeResource' => 'hr',
        // Academics set-up
        'AcademicYearResource' => 'academics',
        'TermResource' => 'academics',
        'ClassLevelResource' => 'academics',
        'SchoolClassResource' => 'academics',
        'SectionResource' => 'academics',
        'HouseResource' => 'academics',
        'ResidencyTypeResource' => 'academics',
        'SubjectResource' => 'academics',
        'CombinationResource' => 'academics',
        'GradingScaleResource' => 'academics',
        // Settings
        'SchoolProfile' => 'settings',
        'SchoolPaySettings' => 'settings',
        'UserResource' => 'settings',
        'AuditTrailResource' => 'settings',
    ];

    public static function hasFullAccess(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user?->hasRole(self::FULL_ACCESS_ROLES) ?? false;
    }

    /**
     * The modules a user may open.
     *
     * @return list<string>
     */
    public static function forUser(?User $user = null): array
    {
        $user ??= auth()->user();

        if (! $user) {
            return [];
        }

        // The platform owner runs SchoolHub itself, not a single school's
        // day-to-day work -- it gets none of these school modules. Its own
        // pages (Schools, Plans, Users, ...) check the role directly.
        if ($user->hasRole('Super Admin')) {
            return [];
        }

        $available = static::availableKeys($user->school);

        if (static::hasFullAccess($user)) {
            return $available;
        }

        // Chosen by the administrator, or the role's defaults.
        $modules = is_array($user->modules)
            ? $user->modules
            : collect($user->getRoleNames())->flatMap(fn ($role) => self::ROLE_DEFAULTS[$role] ?? [])->unique()->all();

        return array_values(array_intersect(static::withImplied($modules), $available));
    }

    /**
     * "Marks for all subjects" is "Exams & results" and more: someone given
     * only the first (a Director of Studies) still opens exams, results
     * and report cards.
     *
     * @param  array<int, string>  $modules
     * @return list<string>
     */
    protected static function withImplied(array $modules): array
    {
        if (in_array('exams_all', $modules, true)) {
            $modules[] = 'exams';
        }

        return array_values(array_unique($modules));
    }

    /**
     * The modules a school can use: all of them, except the primary-only
     * ones (transport) for a secondary school.
     *
     * @return list<string>
     */
    public static function availableKeys(?School $school = null): array
    {
        $keys = array_keys(self::LIST);

        if ($school && $school->school_type === School::TYPE_SECONDARY) {
            $keys = array_values(array_diff($keys, self::PRIMARY_ONLY));
        }

        return $keys;
    }

    public static function allows(string $module): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return in_array($module, once(fn () => static::forUser($user)), true);
    }

    /**
     * May the current user open this resource or page? Anything not mapped
     * (e.g. the dashboard) is open to everyone signed in.
     */
    public static function allowsClass(string $class): bool
    {
        $module = self::CLASS_MAP[class_basename($class)] ?? null;

        return $module === null || static::allows($module);
    }

    /**
     * The modules an administrator can hand out in this school.
     *
     * @return array<string, string> key => "Label — what it covers"
     */
    public static function options(): array
    {
        $available = static::availableKeys(auth()->user()?->school);

        return collect(self::LIST)->only($available)->mapWithKeys(fn ($m, $key) => [$key => $m[0]])->all();
    }

    /** @return array<string, string> */
    public static function descriptions(): array
    {
        return collect(self::LIST)->mapWithKeys(fn ($m, $key) => [$key => $m[1]])->all();
    }
}
