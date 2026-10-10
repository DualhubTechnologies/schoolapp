<?php

namespace App\Support;

use App\Models\User;

/**
 * Which dashboard a user sees. Each user group gets the figures and
 * shortcuts for its own daily work instead of one shared page:
 *
 *   platform     Super Admin with no school — schools and subscriptions
 *   leadership   School Admin / full access — the whole school at a glance
 *   bursar       Fees or finance work — collections and balances
 *   hr           HR and payroll only — staff and salaries
 *   teacher      Exams — own classes, subjects and marks still to enter
 *   parent       A parent's login — their own children's fees and reports
 *   basic        Nothing above — a welcome and where to go
 *
 * A user who does several jobs gets the first that applies, in the order
 * above: money work outranks marks because it is time-critical daily.
 */
class DashboardProfile
{
    public const PLATFORM = 'platform';

    public const LEADERSHIP = 'leadership';

    public const BURSAR = 'bursar';

    public const HR = 'hr';

    public const TEACHER = 'teacher';

    public const PARENT = 'parent';

    public const BASIC = 'basic';

    public const LABELS = [
        self::PLATFORM => 'Platform overview',
        self::LEADERSHIP => 'School overview',
        self::BURSAR => 'Fees & accounts',
        self::HR => 'Staff & payroll',
        self::TEACHER => 'My teaching',
        self::PARENT => 'Parent',
        self::BASIC => 'Welcome',
    ];

    public static function for(?User $user = null): string
    {
        $user ??= auth()->user();

        if (! $user) {
            return self::BASIC;
        }

        return once(fn () => match (true) {
            $user->hasRole('Super Admin') && ! $user->school_id => self::PLATFORM,
            Modules::hasFullAccess($user) => self::LEADERSHIP,
            Modules::allows('fees') || Modules::allows('finance') => self::BURSAR,
            Modules::allows('hr') => self::HR,
            AcademicAccess::teaches() => self::TEACHER,
            $user->hasRole('Parent') => self::PARENT,
            default => self::BASIC,
        });
    }

    public static function is(string ...$profiles): bool
    {
        return in_array(static::for(), $profiles, true);
    }
}
