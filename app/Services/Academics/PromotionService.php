<?php

namespace App\Services\Academics;

use App\Models\AcademicYear;
use App\Models\Promotion;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Year-end promotion: each student moves to the next class, repeats,
 * completes (the last class of primary, O-Level or A-Level) or leaves.
 *
 *   - "Next class" is the next class in the school's order: S1 → S2,
 *     P6 → P7, top nursery class → P1.
 *   - The last class of a curriculum (P7, S4, S6) defaults to "Completed";
 *     S4 students continuing to S5 are switched to "Promote" by hand.
 *   - A student is promoted at most once per academic year, so promoting
 *     S1 before S2 cannot carry the new S2 students on to S3.
 *   - Every move is recorded, so a run can be undone.
 *   - Fee balances need no action: the ledger runs across years, so
 *     anything unpaid shows as arrears next term.
 */
class PromotionService
{
    /** Curricula whose last class ends a stage of schooling. */
    protected const TERMINAL = ['primary', 'o_level', 'a_level'];

    /**
     * The school's classes in progression order.
     *
     * @return Collection<int, SchoolClass>
     */
    public function orderedClasses(int $schoolId): Collection
    {
        return SchoolClass::where('school_id', $schoolId)
            ->with('classLevel')
            ->get()
            ->sortBy(fn (SchoolClass $c) => [$c->classLevel?->sort_order ?? 99, $c->number() ?? 99, $c->name])
            ->values();
    }

    public function nextClass(SchoolClass $class): ?SchoolClass
    {
        $classes = $this->orderedClasses($class->school_id);
        $i = $classes->search(fn ($c) => $c->is($class));

        return $i === false ? null : $classes->get($i + 1);
    }

    /**
     * Is this the last class of its stage (P7, S4, S6)?
     */
    public function isFinalClass(SchoolClass $class): bool
    {
        $curriculum = $class->curriculum();

        if (! in_array($curriculum, self::TERMINAL, true)) {
            return $this->nextClass($class) === null;
        }

        $next = $this->nextClass($class);

        return $next === null || $next->curriculum() !== $curriculum;
    }

    public function defaultAction(SchoolClass $class): string
    {
        return $this->isFinalClass($class) ? 'complete' : 'promote';
    }

    /**
     * Active students still to be promoted this year (anyone already moved
     * this academic year is left out).
     *
     * @return Collection<int, Student>
     */
    public function pendingStudents(SchoolClass $class, ?AcademicYear $year): Collection
    {
        $done = Promotion::where('school_id', $class->school_id)
            ->where('academic_year_id', $year?->getKey())
            ->whereNull('reversed_at')
            ->pluck('student_id');

        return Student::where('school_class_id', $class->getKey())
            ->where('status', 'active')
            ->whereNotIn('id', $done)
            ->with('section')
            ->orderBy('name')
            ->get();
    }

    /**
     * The same-named stream in the target class (S1 "A" → S2 "A").
     */
    public function matchingSection(?Section $section, ?SchoolClass $target): ?int
    {
        if (! $section || ! $target) {
            return null;
        }

        return Section::where('school_class_id', $target->getKey())
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($section->name)])
            ->value('id');
    }

    /**
     * Carry out the decisions for one class.
     *
     * @param  array<int, string>  $decisions  student id => promote|probation|repeat|complete|leave
     * @param  array<int, array>  $advice  student id => PromotionAdvisor result, stored with the move
     * @return array{batch: string, promote: int, probation: int, repeat: int, complete: int, leave: int}
     */
    public function run(SchoolClass $class, array $decisions, ?SchoolClass $target, ?AcademicYear $year, array $advice = []): array
    {
        $batch = (string) Str::uuid();
        $counts = ['batch' => $batch, 'promote' => 0, 'probation' => 0, 'repeat' => 0, 'complete' => 0, 'leave' => 0];
        $students = $this->pendingStudents($class, $year)->keyBy('id');

        DB::transaction(function () use ($class, $decisions, $target, $year, $batch, $students, $advice, &$counts) {
            $targetCurriculum = $target?->curriculum();

            foreach ($decisions as $studentId => $action) {
                $student = $students->get((int) $studentId);

                if (! $student || ! array_key_exists($action, Promotion::ACTIONS)) {
                    continue;
                }

                // Probation is a promotion, recorded as such.
                $movesUp = in_array($action, ['promote', 'probation'], true);

                if ($movesUp && ! $target) {
                    continue; // nowhere to promote to
                }

                $toSection = $movesUp ? $this->matchingSection($student->section, $target) : null;
                $studentAdvice = $advice[$student->getKey()] ?? null;

                Promotion::create([
                    'school_id' => $class->school_id,
                    'batch' => $batch,
                    'academic_year_id' => $year?->getKey(),
                    'student_id' => $student->getKey(),
                    'action' => $action,
                    'recommendation' => $studentAdvice['recommendation'] ?? null,
                    'reason' => isset($studentAdvice['reason']) ? mb_substr($studentAdvice['reason'], 0, 250) : null,
                    'average' => $studentAdvice['average'] ?? null,
                    'from_class_id' => $class->getKey(),
                    'from_section_id' => $student->section_id,
                    'from_status' => $student->status,
                    'from_combination_id' => $student->combination_id,
                    'to_class_id' => $movesUp ? $target->getKey() : ($action === 'repeat' ? $class->getKey() : null),
                    'to_section_id' => $toSection,
                    'performed_by' => auth()->user()?->name,
                ]);

                match ($action) {
                    'promote', 'probation' => $student->update([
                        'school_class_id' => $target->getKey(),
                        'section_id' => $toSection,
                        // A combination only means something at A-Level.
                        'combination_id' => $targetCurriculum === 'a_level' ? $student->combination_id : null,
                    ]),
                    'complete' => $student->update(['status' => 'graduated']),
                    'leave' => $student->update(['status' => 'withdrawn']),
                    default => null, // repeat: stays in the class
                };

                // O-Level electives do not carry into A-Level (or primary into secondary).
                if ($movesUp && $targetCurriculum !== $class->curriculum()) {
                    $student->electives()->detach();
                }

                $counts[$action]++;
            }
        });

        return $counts;
    }

    /**
     * Put everyone in a run back where they were.
     */
    public function undo(string $batch, int $schoolId): int
    {
        return DB::transaction(function () use ($batch, $schoolId) {
            $rows = Promotion::where('school_id', $schoolId)->where('batch', $batch)->whereNull('reversed_at')->get();

            foreach ($rows as $row) {
                $row->student?->update([
                    'school_class_id' => $row->from_class_id,
                    'section_id' => $row->from_section_id,
                    'status' => $row->from_status,
                    'combination_id' => $row->from_combination_id,
                ]);
                $row->update(['reversed_at' => now()]);
            }

            return $rows->count();
        });
    }
}
