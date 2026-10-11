<?php

namespace App\Support;

use App\Models\School;
use App\Models\Student;
use Illuminate\Support\Str;

/**
 * The next registration (admission) number for a new learner, in the
 * style the school chose under Settings:
 *
 *   initials_year  GHS/2026/0001  (the usual style in Ugandan schools)
 *   year           2026/0001
 *   plain          001, 002 ...   (SchoolHub's first style)
 *
 * The year is the year of admission and never changes when the learner
 * is promoted. The serial starts again at 0001 each year (or at the
 * school's chosen starting number, for a school that joins mid-year).
 * Numbers already given, typed in or imported are never changed.
 */
class AdmissionNumbers
{
    public const STYLES = [
        'initials_year' => 'School initials / year / number, e.g. GHS/2026/0001',
        'year' => 'Year / number, e.g. 2026/0001',
        'plain' => 'Number only, e.g. 001',
    ];

    /** Words left out of a school's initials. */
    protected const SKIP_WORDS = ['of', 'and', 'the', 'for', '&'];

    public static function next(School $school, ?int $year = null): string
    {
        $style = $school->admission_no_style ?: 'initials_year';

        if ($style === 'plain') {
            return static::nextPlain($school);
        }

        $prefix = static::prefix($school, $year ?? (int) now()->format('Y'));
        $highest = Student::withoutGlobalScopes()
            ->where('school_id', $school->getKey())
            ->where('admission_no', 'like', $prefix.'%')   // the prefix is letters, digits and "/" only
            ->pluck('admission_no')
            ->map(fn (string $number): int => (int) Str::after($number, $prefix))
            ->max();

        $serial = $highest !== null ? $highest + 1 : max(1, (int) $school->admission_no_start);

        return $prefix.str_pad((string) $serial, 4, '0', STR_PAD_LEFT);
    }

    /** e.g. "GHS/2026/" or "2026/". */
    public static function prefix(School $school, int $year): string
    {
        return ($school->admission_no_style ?: 'initials_year') === 'initials_year'
            ? static::initials($school).'/'.$year.'/'
            : $year.'/';
    }

    /**
     * The school's chosen initials, or the first letter of each word of its
     * name before any " - place": "GLOBAL HIGH SCHOOL - OMUNGYENYI" is GHS.
     */
    public static function initials(School $school): string
    {
        $chosen = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $school->admission_no_initials));

        if ($chosen !== '') {
            return $chosen;
        }

        $name = trim(Str::before((string) $school->name, ' - '));
        $letters = collect(preg_split('/[\s\-\/]+/', $name) ?: [])
            ->reject(fn (string $word): bool => $word === '' || in_array(strtolower($word), self::SKIP_WORDS, true))
            ->map(fn (string $word): string => strtoupper(mb_substr(preg_replace('/[^A-Za-z0-9]/', '', $word), 0, 1)))
            ->implode('');

        return substr($letters, 0, 6) ?: 'SCH';
    }

    /** SchoolHub's first style: one more than the last number given. */
    protected static function nextPlain(School $school): string
    {
        $last = Student::withoutGlobalScopes()
            ->where('school_id', $school->getKey())
            ->orderByDesc('id')
            ->value('admission_no');

        $next = ((int) preg_replace('/\D/', '', (string) $last)) + 1;

        return str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
