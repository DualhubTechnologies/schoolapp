<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\ClassLevel;
use App\Models\FinanceCategory;
use App\Models\ResidencyType;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Models\User;
use App\Services\Academics\CurriculumSetup;
use App\Support\Modules;
use App\Support\PayrollDefaults;
use App\Support\SchoolType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The first-sign-in offer to a newly registered school: fill in the
 * standard Ugandan school structure so it can start work straight away.
 *
 *   classes        P.1-P.7 / S.1-S.6 / Baby-Top Class, no streams
 *   calendar       this year and its three terms, the right one current
 *   curriculum     subjects, grading scales, A-Level combinations
 *   residency      Day and/or Boarding
 *   payroll        the usual allowance and deduction types
 *   finance        income and expense categories
 *
 * Everything is an ordinary record the school can edit or delete. Safe
 * to run twice: it only adds what is missing.
 */
class SchoolStarterSetup
{
    public const CHOICE_RECOMMENDED = 'recommended';

    public const CHOICE_SKIPPED = 'skipped';

    /** Ages and exams shown next to each section in the offer. */
    public const SECTION_HINTS = [
        'nursery' => 'Baby, Middle and Top Class',
        'primary' => 'P.1 to P.7, ending with PLE',
        'o_level' => 'S.1 to S.4, ending with UCE',
        'a_level' => 'S.5 and S.6, ending with UACE',
    ];

    public const RESIDENCY = [
        'Day' => 'Learners who go home at the end of each day.',
        'Boarding' => 'Learners who live at school during the term.',
    ];

    public function __construct(protected CurriculumSetup $curriculum) {}

    /**
     * Whether this user should be offered the setup: a school's
     * administrator, before the school has answered.
     */
    public static function isOfferedTo(?User $user): bool
    {
        if (! $user?->school_id || $user->hasRole('Super Admin') || ! Modules::hasFullAccess($user)) {
            return false;
        }

        return $user->school?->setup_completed_at === null;
    }

    /**
     * @param  list<string>  $sections  curriculum keys, e.g. ['nursery', 'primary']
     * @param  string  $boardingType  day | boarding | mixed (School::BOARDING_TYPES)
     * @return array{classes: int, terms: int, subjects: int, scales: int, combinations: int, residency: int, year: string}
     */
    public function run(School $school, array $sections, string $boardingType): array
    {
        $sections = array_values(array_intersect(SchoolType::keys($school), $sections));

        return DB::transaction(function () use ($school, $sections, $boardingType) {
            $classes = $this->classes($school, $sections);
            [$year, $terms] = $this->calendar($school);
            $residency = $this->residency($school, $boardingType);
            $curriculum = $this->curriculum->run($school);

            PayrollDefaults::allowances($school->getKey());
            PayrollDefaults::deductions($school->getKey());
            FinanceCategory::ensureDefaults($school->getKey());

            $school->update([
                'boarding_type' => $boardingType,
                'setup_completed_at' => now(),
                'setup_choice' => self::CHOICE_RECOMMENDED,
            ]);

            return [
                'classes' => $classes,
                'terms' => $terms,
                'subjects' => $curriculum['subjects'],
                'scales' => $curriculum['scales'],
                'combinations' => $curriculum['combinations'],
                'residency' => $residency,
                'year' => $year->name,
            ];
        });
    }

    /**
     * The school will set itself up by hand; the dashboard checklist
     * guides it from here.
     */
    public function skip(School $school): void
    {
        $school->update([
            'setup_completed_at' => now(),
            'setup_choice' => self::CHOICE_SKIPPED,
        ]);
    }

    /**
     * This year's terms as the offer describes them.
     *
     * @return list<array{name: string, starts: Carbon, ends: Carbon}>
     */
    public static function termsFor(?Carbon $today = null): array
    {
        $year = ($today ?? today())->year;
        $official = config("academics.calendar.{$year}");

        return collect($official ?? config('academics.calendar_pattern'))
            ->map(fn (array $term): array => [
                'name' => $term[0],
                'starts' => Carbon::parse($official ? $term[1] : "{$year}-{$term[1]}"),
                'ends' => Carbon::parse($official ? $term[2] : "{$year}-{$term[2]}"),
            ])
            ->all();
    }

    /**
     * Whether termsFor() comes from the Ministry calendar or the usual
     * pattern (so the offer can say the dates need checking).
     */
    public static function hasOfficialCalendar(?Carbon $today = null): bool
    {
        return config('academics.calendar.'.($today ?? today())->year) !== null;
    }

    /**
     * The term a school joining today works in: the latest one that has
     * opened, or the first term before the year starts. A school joining
     * in the holiday after Term 1 is still closing Term 1's books.
     *
     * @param  list<array{name: string, starts: Carbon, ends: Carbon}>  $terms
     */
    public static function currentTermIndex(array $terms, ?Carbon $today = null): int
    {
        $today ??= today();
        $index = 0;

        foreach ($terms as $i => $term) {
            if ($term['starts']->lte($today)) {
                $index = $i;
            }
        }

        return $index;
    }

    /**
     * @param  list<string>  $sections
     */
    protected function classes(School $school, array $sections): int
    {
        $added = 0;
        $position = (int) SchoolClass::where('school_id', $school->getKey())->max('level');

        foreach (array_keys(config('academics.curricula')) as $curriculum) {
            if (! in_array($curriculum, $sections, true)) {
                continue;
            }

            $level = $this->levelFor($school, $curriculum);

            foreach (config("academics.classes.{$curriculum}", []) as $name) {
                $class = SchoolClass::firstOrCreate(
                    ['school_id' => $school->getKey(), 'name' => $name],
                    ['class_level_id' => $level->getKey(), 'level' => ++$position],
                );

                $added += (int) $class->wasRecentlyCreated;
            }
        }

        return $added;
    }

    /**
     * The school's level for a curriculum -- normally created at
     * registration (SchoolObserver); added here if it was deleted since.
     */
    protected function levelFor(School $school, string $curriculum): ClassLevel
    {
        $existing = ClassLevel::where('school_id', $school->getKey())->where('curriculum', $curriculum)->first();

        if ($existing) {
            return $existing;
        }

        $name = array_search($curriculum, ClassLevel::DEFAULT_CURRICULA, true) ?: config("academics.curricula.{$curriculum}");

        return ClassLevel::create([
            'school_id' => $school->getKey(),
            'name' => $name,
            'curriculum' => $curriculum,
            'sort_order' => (int) ClassLevel::where('school_id', $school->getKey())->max('sort_order') + 1,
        ]);
    }

    /**
     * @return array{0: AcademicYear, 1: int} the year and how many terms were added
     */
    protected function calendar(School $school): array
    {
        $terms = static::termsFor();
        $current = static::currentTermIndex($terms);
        $name = (string) today()->year;

        $year = AcademicYear::firstOrCreate(
            ['school_id' => $school->getKey(), 'name' => $name],
            ['start_date' => $terms[0]['starts'], 'end_date' => end($terms)['ends']],
        );

        if (! AcademicYear::where('school_id', $school->getKey())->where('is_current', true)->exists()) {
            $year->update(['is_current' => true]);
        }

        $added = 0;
        $hasCurrentTerm = Term::where('school_id', $school->getKey())->where('is_current', true)->exists();

        foreach ($terms as $i => $term) {
            $model = Term::firstOrCreate(
                ['school_id' => $school->getKey(), 'academic_year_id' => $year->getKey(), 'sequence' => $i + 1],
                [
                    'name' => $term['name'],
                    'start_date' => $term['starts'],
                    'end_date' => $term['ends'],
                    'is_current' => ! $hasCurrentTerm && $i === $current,
                ],
            );

            $added += (int) $model->wasRecentlyCreated;
        }

        return [$year, $added];
    }

    protected function residency(School $school, string $boardingType): int
    {
        $names = match ($boardingType) {
            'boarding' => ['Boarding'],
            'mixed' => ['Day', 'Boarding'],
            default => ['Day'],
        };

        $added = 0;

        foreach ($names as $name) {
            $type = ResidencyType::firstOrCreate(
                ['school_id' => $school->getKey(), 'name' => $name],
                ['description' => self::RESIDENCY[$name], 'is_active' => true],
            );

            $added += (int) $type->wasRecentlyCreated;
        }

        return $added;
    }
}
