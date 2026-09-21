<?php

namespace App\Support;

use App\Models\Staff;

/**
 * Who does what in academics:
 *
 *   School Admin  sets up subjects, grading, exams; enters any marks;
 *                 writes head-teacher comments; prints report cards
 *   Teacher       enters marks for the class subjects assigned to them,
 *                 views results and writes class-teacher comments
 */
class AcademicAccess
{
    public static function manages(): bool
    {
        return auth()->user()?->hasRole('School Admin') ?? false;
    }

    public static function teaches(): bool
    {
        return auth()->user()?->hasRole(['School Admin', 'Teacher']) ?? false;
    }

    /**
     * The signed-in user's staff record, if they have one.
     */
    public static function staffId(): ?int
    {
        $userId = auth()->id();

        return $userId ? once(fn () => Staff::where('user_id', $userId)->value('id')) : null;
    }

    /**
     * May the user enter marks for a class subject taught by $teacherId?
     * Admins may enter any; a teacher only those assigned to them.
     */
    public static function canEnterMarksFor(?int $teacherId): bool
    {
        if (static::manages()) {
            return true;
        }

        return static::teaches() && $teacherId !== null && $teacherId === static::staffId();
    }
}
