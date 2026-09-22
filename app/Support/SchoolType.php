<?php

namespace App\Support;

use App\Models\School;

/**
 * What a school's type allows. A primary school sees only nursery and
 * primary; a secondary school only O-Level and A-Level. Every curriculum
 * list in the app (subjects, exams, class levels, grading scales,
 * combinations) goes through here, so the other side never appears.
 */
class SchoolType
{
    public const CURRICULA = [
        School::TYPE_PRIMARY => ['nursery', 'primary'],
        School::TYPE_SECONDARY => ['o_level', 'a_level'],
    ];

    public static function school(): ?School
    {
        $schoolId = auth()->user()?->school_id;

        return $schoolId ? once(fn () => School::find($schoolId)) : null;
    }

    /**
     * Curriculum keys the school may use. A school without a type (or a
     * Super Admin outside any school) sees them all.
     *
     * @return list<string>
     */
    public static function keys(?School $school = null): array
    {
        $school ??= static::school();

        return self::CURRICULA[$school?->school_type] ?? array_keys(config('academics.curricula'));
    }

    /**
     * The allowed curricula as options: key => label.
     *
     * @return array<string, string>
     */
    public static function curricula(?School $school = null): array
    {
        return array_intersect_key(config('academics.curricula'), array_flip(static::keys($school)));
    }

    public static function allows(?string $curriculum, ?School $school = null): bool
    {
        return $curriculum !== null && in_array($curriculum, static::keys($school), true);
    }

    public static function isPrimary(?School $school = null): bool
    {
        return ($school ?? static::school())?->school_type === School::TYPE_PRIMARY;
    }

    public static function isSecondary(?School $school = null): bool
    {
        return ($school ?? static::school())?->school_type === School::TYPE_SECONDARY;
    }
}
