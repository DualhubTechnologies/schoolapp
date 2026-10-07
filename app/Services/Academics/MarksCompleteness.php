<?php

namespace App\Services\Academics;

use App\Models\Assessment;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;

/**
 * Which exams have not been entered yet for a term, so report cards are
 * not shared with parents half-finished.
 *
 * A subject counts as taught in a class once any of its learners has a
 * mark in it this term. From then on, every one of the term's exams for
 * that class's curriculum should have marks in that subject; one with none
 * at all is missing. (Single learners without a mark are not listed:
 * absences and electives make those normal.)
 */
class MarksCompleteness
{
    /**
     * @return list<array{class: string, missing: list<string>}>
     */
    public function missingFor(Term $term): array
    {
        $assessments = Assessment::where('school_id', $term->school_id)
            ->where('term_id', $term->getKey())
            ->orderBy('sort_order')
            ->orderBy('held_on')
            ->orderBy('id')
            ->get();

        if ($assessments->isEmpty()) {
            return [];
        }

        // One row per class, subject and exam that has at least one mark.
        $entered = Mark::join('students', 'students.id', '=', 'marks.student_id')
            ->whereIn('marks.assessment_id', $assessments->pluck('id'))
            ->where('students.status', 'active')
            ->select('students.school_class_id', 'marks.subject_id', 'marks.assessment_id')
            ->distinct()
            ->get()
            ->groupBy('school_class_id');

        $classes = SchoolClass::where('school_id', $term->school_id)
            ->whereIn('id', $entered->keys())
            ->with('classLevel')
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        $subjects = Subject::whereIn('id', $entered->flatten(1)->pluck('subject_id')->unique())->pluck('name', 'id');

        $gaps = [];

        foreach ($classes as $class) {
            $rows = $entered->get($class->getKey(), collect());
            $have = $rows->map(fn ($r) => "{$r->subject_id}:{$r->assessment_id}")->flip();
            $curriculum = $class->curriculum();
            $missing = [];

            foreach ($rows->pluck('subject_id')->unique() as $subjectId) {
                foreach ($assessments as $assessment) {
                    if (! $assessment->appliesTo($curriculum) || ! $assessment->coversClass((int) $class->getKey()) || ! $assessment->coversSubject((int) $subjectId)) {
                        continue;
                    }

                    if (! $have->has("{$subjectId}:{$assessment->getKey()}")) {
                        $missing[] = ($subjects[$subjectId] ?? 'Subject').' — '.$assessment->name;
                    }
                }
            }

            if ($missing) {
                sort($missing);
                $gaps[] = ['class' => (string) $class->name, 'missing' => $missing];
            }
        }

        return $gaps;
    }
}
