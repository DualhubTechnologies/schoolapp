<?php

namespace App\Services\Academics;

use App\Models\Assessment;
use App\Models\GradingScale;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermReport;
use Illuminate\Support\Collection;

/**
 * Turns a class's marks for a term into results: a final score and grade
 * per subject, then the overall result the curriculum uses --
 *
 *   primary   aggregate of the four core subjects (1 = D1 ... 9 = F9) and
 *             the PLE-style division
 *   o_level   average score and overall achievement level (A–E)
 *   a_level   points: principal subjects A–F (6–0) + subsidiaries (1
 *             each), out of 20
 *
 * and positions in the class and stream.
 *
 * A subject's term score is the weighted average of the term's
 * assessments the student has a score in, each converted to a percentage
 * of its "out of" value. When none of those assessments carries a weight,
 * they count equally.
 */
class ResultsCalculator
{
    /** @var array<string, GradingScale|null> */
    protected array $scales = [];

    /**
     * @return array{
     *     class: SchoolClass, term: Term, curriculum: ?string,
     *     assessments: Collection<int, Assessment>, subjects: Collection<int, Subject>,
     *     rows: Collection<int, array>, subject_stats: array<int, array>, summary: array
     * }
     */
    public function forClass(SchoolClass $class, Term $term, ?int $sectionId = null): array
    {
        $class->loadMissing(['classLevel', 'subjects']);
        $curriculum = $class->curriculum();

        $assessments = Assessment::where('school_id', $class->school_id)
            ->where('term_id', $term->getKey())
            ->where(fn ($q) => $q->whereNull('curriculum')->orWhere('curriculum', $curriculum))
            ->orderBy('sort_order')
            ->orderBy('held_on')
            ->orderBy('id')
            ->get();

        $students = Student::where('school_class_id', $class->getKey())
            ->where('status', 'active')
            ->with(['section', 'combination.subjects', 'combination.subsidiary', 'electives'])
            ->orderBy('name')
            ->get();

        $marks = Mark::whereIn('assessment_id', $assessments->pluck('id'))
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->groupBy(fn (Mark $m) => "{$m->student_id}:{$m->subject_id}");

        $subjects = $class->subjects;
        $reports = TermReport::where('term_id', $term->getKey())->whereIn('student_id', $students->pluck('id'))->get()->keyBy('student_id');

        $rows = $students->mapWithKeys(fn (Student $student) => [
            $student->getKey() => $this->studentRow($student, $curriculum, $class->school_id, $subjects, $assessments, $marks, $reports->get($student->getKey())),
        ]);

        $this->rank($rows, $curriculum, 'position', 'out_of');

        // preserveKeys: rows are keyed by student id, and the stream
        // positions are copied back by that key.
        foreach ($rows->groupBy(fn ($r) => $r['student']->section_id ?? 0, preserveKeys: true) as $sectionRows) {
            $this->rank($sectionRows, $curriculum, 'stream_position', 'stream_out_of');
            foreach ($sectionRows as $id => $row) {
                $rows[$id] = $row;
            }
        }

        if ($sectionId) {
            $rows = $rows->filter(fn ($r) => (int) $r['student']->section_id === $sectionId);
        }

        $rows = $rows->sortBy(fn ($r) => [$r['position'] ?? PHP_INT_MAX, $r['student']->name])->values();

        return [
            'class' => $class,
            'term' => $term,
            'curriculum' => $curriculum,
            'assessments' => $assessments,
            'subjects' => $subjects,
            'rows' => $rows,
            'subject_stats' => $this->subjectStats($rows, $subjects),
            'summary' => $this->summary($rows, $curriculum),
        ];
    }

    protected function studentRow(Student $student, ?string $curriculum, int $schoolId, Collection $subjects, Collection $assessments, Collection $marks, ?TermReport $report): array
    {
        $results = [];

        foreach ($subjects as $subject) {
            $scores = [];
            $weighted = 0.0;
            $weights = 0.0;
            $plain = [];
            $comment = null;

            foreach ($assessments as $assessment) {
                $mark = $marks->get("{$student->getKey()}:{$subject->getKey()}")?->firstWhere('assessment_id', $assessment->getKey());

                if (! $mark) {
                    continue;
                }

                $pct = $mark->score !== null && (float) $assessment->max_score > 0
                    ? min(100, (float) $mark->score / (float) $assessment->max_score * 100)
                    : null;

                $scores[$assessment->getKey()] = ['raw' => $mark->score, 'pct' => $pct, 'absent' => $mark->is_absent];
                $comment = $mark->comment ?: $comment;

                if ($pct !== null) {
                    $weighted += $pct * (float) $assessment->weight;
                    $weights += (float) $assessment->weight;
                    $plain[] = $pct;
                }
            }

            if (! $scores) {
                continue; // the student does not take this subject, or has no marks yet
            }

            $final = $weights > 0 ? $weighted / $weights : ($plain ? array_sum($plain) / count($plain) : null);
            $band = $final === null ? null : $this->scale($schoolId, $curriculum, $this->purposeFor($curriculum, $subject))?->bandFor($final);

            $results[$subject->getKey()] = [
                'subject' => $subject,
                'scores' => $scores,
                'final' => $final === null ? null : round($final, 1),
                'grade' => $band?->grade,
                'value' => $band ? (float) $band->value : null,
                'descriptor' => $band?->descriptor,
                'comment' => $comment,
            ];
        }

        $finals = collect($results)->pluck('final')->filter(fn ($v) => $v !== null);

        $row = [
            'student' => $student,
            'subjects' => $results,
            'total' => round($finals->sum(), 1),
            'count' => $finals->count(),
            'average' => $finals->count() ? round($finals->avg(), 1) : null,
            'report' => $report,
            'position' => null,
            'out_of' => null,
            'stream_position' => null,
            'stream_out_of' => null,
        ];

        return $row + match ($curriculum) {
            'primary' => $this->primaryOverall($results),
            'a_level' => $this->aLevelOverall($student, $results),
            'o_level', 'nursery' => $this->averageOverall($schoolId, $curriculum, $row['average']),
            default => [],
        };
    }

    /**
     * Aggregate of the four core subjects and the division. Without a
     * grade in every core subject there is no aggregate (shown as X).
     */
    protected function primaryOverall(array $results): array
    {
        $core = collect($results)->filter(fn ($r) => $r['subject']->category === 'core');
        $graded = $core->filter(fn ($r) => $r['value'] !== null);

        if ($core->isEmpty()) {
            return ['aggregate' => null, 'division' => null];
        }

        if ($graded->count() < 4) {
            return ['aggregate' => null, 'division' => 'X'];
        }

        // Four core subjects is the PLE set; if a school marks more, the
        // best four count.
        $aggregate = (int) $graded->pluck('value')->sort()->take(4)->sum();

        $division = collect(config('academics.primary_divisions'))
            ->first(fn ($d) => $aggregate >= $d[1] && $aggregate <= $d[2])[0] ?? 'Ungraded';

        return ['aggregate' => $aggregate, 'division' => $division];
    }

    /**
     * Points out of 20: the three principal subjects (the student's
     * combination, or their best three) plus General Paper and one other
     * subsidiary.
     */
    protected function aLevelOverall(Student $student, array $results): array
    {
        $all = collect($results)->filter(fn ($r) => $r['value'] !== null);

        $principalIds = $student->combination?->subjects->pluck('id')->all();
        $principals = $all->filter(fn ($r) => $r['subject']->category === 'principal')
            ->when($principalIds, fn ($c) => $c->filter(fn ($r) => in_array($r['subject']->getKey(), $principalIds, true)))
            ->sortByDesc('final')
            ->take(3);

        $chosenSub = $student->electives->firstWhere('category', 'subsidiary')?->getKey()
            ?? $student->combination?->subsidiary_subject_id;

        $subsidiaries = $all->filter(fn ($r) => $r['subject']->category === 'subsidiary');
        $gp = $subsidiaries->first(fn ($r) => stripos($r['subject']->name, 'general paper') !== false);
        $other = $subsidiaries
            ->reject(fn ($r) => $gp && $r['subject']->is($gp['subject']))
            ->when($chosenSub, fn ($c) => $c->filter(fn ($r) => $r['subject']->getKey() === $chosenSub))
            ->sortByDesc('final')
            ->first();
        $subs = collect([$gp, $other])->filter();

        return [
            'points' => $principals->isEmpty() && $subs->isEmpty() ? null : (int) ($principals->sum('value') + $subs->sum('value')),
            'principal_grades' => $principals->map(fn ($r) => $r['subject']->label() . ' ' . $r['grade'])->implode(', '),
            'result_code' => $principals->pluck('grade')->implode('') . ($subs->isNotEmpty() ? '/' . $subs->map(fn ($r) => (int) $r['value'])->implode('') : ''),
            'counted_subject_ids' => $principals->keys()->merge($subs->keys())->all(),
        ];
    }

    /**
     * New lower-secondary curriculum (and nursery): the overall level is
     * the average score read against the subject scale.
     */
    protected function averageOverall(int $schoolId, string $curriculum, ?float $average): array
    {
        $band = $this->scale($schoolId, $curriculum, 'subject')?->bandFor($average);

        return ['overall_grade' => $band?->grade, 'overall_descriptor' => $band?->descriptor];
    }

    /**
     * Competition ranking ("1, 2, 2, 4"): primary by aggregate (lowest
     * first), A-Level by points, everyone by average as the tie-break.
     * Students with no results are not ranked.
     */
    protected function rank(Collection $rows, ?string $curriculum, string $key, string $outOfKey): void
    {
        $ranked = $rows->filter(fn ($r) => $r['average'] !== null);

        $score = fn (array $r) => match ($curriculum) {
            'primary' => [$r['aggregate'] === null ? 1 : 0, $r['aggregate'] ?? 0, -($r['average'] ?? 0)],
            'a_level' => [-($r['points'] ?? -1), -($r['average'] ?? 0)],
            default => [-($r['average'] ?? 0)],
        };

        $sorted = $ranked->sortBy(fn ($r) => $score($r))->keys()->values();
        $outOf = $sorted->count();
        $position = 0;
        $previous = null;

        foreach ($sorted as $i => $id) {
            $current = $score($rows[$id]);

            if ($current !== $previous) {
                $position = $i + 1;
                $previous = $current;
            }

            $row = $rows[$id];
            $row[$key] = $position;
            $row[$outOfKey] = $outOf;
            $rows[$id] = $row;
        }
    }

    protected function subjectStats(Collection $rows, Collection $subjects): array
    {
        $stats = [];

        foreach ($subjects as $subject) {
            $results = $rows->map(fn ($r) => $r['subjects'][$subject->getKey()] ?? null)->filter(fn ($r) => $r && $r['final'] !== null);

            if ($results->isEmpty()) {
                continue;
            }

            $stats[$subject->getKey()] = [
                'count' => $results->count(),
                'average' => round($results->avg('final'), 1),
                'highest' => $results->max('final'),
                'lowest' => $results->min('final'),
                'grades' => $results->countBy('grade')->sortKeys()->all(),
            ];
        }

        return $stats;
    }

    protected function summary(Collection $rows, ?string $curriculum): array
    {
        $ranked = $rows->filter(fn ($r) => $r['average'] !== null);

        return [
            'students' => $rows->count(),
            'with_results' => $ranked->count(),
            'class_average' => $ranked->isEmpty() ? null : round($ranked->avg('average'), 1),
            'distribution' => match ($curriculum) {
                'primary' => $ranked->countBy(fn ($r) => $r['division'] ?? '—')->all(),
                'a_level' => $ranked->countBy(fn ($r) => $r['points'] === null ? '—' : $r['points'] . ' pts')->sortKeysDesc()->all(),
                default => $ranked->countBy(fn ($r) => $r['overall_grade'] ?? '—')->sortKeys()->all(),
            },
        ];
    }

    protected function purposeFor(?string $curriculum, Subject $subject): string
    {
        if ($curriculum === 'a_level') {
            return $subject->category === 'subsidiary' ? 'subsidiary' : 'principal';
        }

        return 'subject';
    }

    protected function scale(int $schoolId, ?string $curriculum, string $purpose): ?GradingScale
    {
        return $this->scales["{$schoolId}:{$curriculum}:{$purpose}"] ??= GradingScale::where('school_id', $schoolId)
            ->where('curriculum', $curriculum)
            ->where('purpose', $purpose)
            ->with('bands')
            ->first();
    }
}
