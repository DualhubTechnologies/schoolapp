<?php

namespace App\Services\Dashboard;

use App\Models\Assessment;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Support\Collection;

/**
 * A teacher's classes and subjects this term, and how far each mark sheet
 * has got for the latest open exam. Learners are counted the way the mark
 * sheet lists them: everyone for a compulsory subject, otherwise those who
 * chose it (or everyone, if no choices are recorded yet).
 */
class TeacherLoad
{
    /**
     * @return Collection<int, array{class: SchoolClass, subject: Subject, assessment: ?Assessment, learners: int, entered: int, studentIds: list<int>}>
     */
    public static function rows(int $schoolId, int $staffId): Collection
    {
        return once(function () use ($schoolId, $staffId) {
            $term = Term::current($schoolId);

            $assessments = $term
                ? Assessment::where('school_id', $schoolId)->where('term_id', $term->getKey())
                    ->where('status', '!=', 'locked')
                    ->orderByDesc('held_on')->orderByDesc('sort_order')->orderByDesc('id')
                    ->get()
                : collect();

            $classes = SchoolClass::where('school_id', $schoolId)
                ->whereHas('subjects', fn ($q) => $q->where('class_subject.teacher_id', $staffId))
                ->with(['classLevel', 'subjects' => fn ($q) => $q->where('class_subject.teacher_id', $staffId)])
                ->orderBy('level')->orderBy('name')
                ->get();

            return $classes->flatMap(function (SchoolClass $class) use ($assessments) {
                $students = Student::where('school_class_id', $class->getKey())
                    ->where('status', 'active')
                    ->with(['combination.subjects', 'electives'])
                    ->get();

                $assessment = $assessments->first(fn (Assessment $a) => $a->appliesTo($class->curriculum()));

                return $class->subjects->map(function (Subject $subject) use ($class, $students, $assessment) {
                    $takes = $subject->pivot->is_compulsory
                        ? $students
                        : $students->filter(fn (Student $s) => $s->electives->contains('id', $subject->id)
                            || $s->combination?->subjects->contains('id', $subject->id)
                            || $s->combination?->subsidiary_subject_id === $subject->id);

                    if ($takes->isEmpty()) {
                        $takes = $students;
                    }

                    $ids = $takes->pluck('id')->all();

                    return [
                        'class' => $class,
                        'subject' => $subject,
                        'assessment' => $assessment,
                        'learners' => count($ids),
                        'entered' => $assessment && $ids
                            ? Mark::where('assessment_id', $assessment->getKey())
                                ->where('subject_id', $subject->getKey())
                                ->whereIn('student_id', $ids)
                                ->where(fn ($q) => $q->whereNotNull('score')->orWhere('is_absent', true))
                                ->count()
                            : 0,
                        'studentIds' => $ids,
                    ];
                });
            })->values();
        });
    }
}
