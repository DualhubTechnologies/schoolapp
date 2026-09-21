<?php

namespace App\Support;

/**
 * Who may see staff pay. Salaries are confidential: only the school's
 * administrators and accountants -- never teachers, parents or students.
 */
class PayrollAccess
{
    public const ROLES = ['School Admin', 'Accountant'];

    public static function allowed(): bool
    {
        return auth()->user()?->hasRole(self::ROLES) ?? false;
    }

    /**
     * Stop the request unless the user handles payroll for this school.
     */
    public static function authorize(?int $schoolId): void
    {
        $user = auth()->user();

        abort_unless(
            $user && ($user->hasRole('Super Admin') || (static::allowed() && (int) $user->school_id === (int) $schoolId)),
            403,
        );
    }
}
