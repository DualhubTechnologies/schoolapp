<?php

namespace App\Services\Academics;

use App\Models\AcademicYear;
use App\Models\PromotionRule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Support\Collection;

/**
 * Recommends each student's end-of-year outcome from the school's
 * promotion rules, the way a Ugandan school's staff meeting would:
 *
 *   promote     met every condition
 *   probation   close: average just under the line, or a required
 *               subject failed despite a fair average
 *   repeat      clearly below the line ("advised to repeat")
 *   no_results  no marks this year -- the head teacher decides
 *
 * It only recommends. The final decision per student is always made on
 * the promotion screen.
 */
class PromotionAdvisor
{
    /** @var array<int, Collection> term id => results rows keyed by student id */
    protected array $termResults = [];

    public function __construct(protected ResultsCalculator $calculator) {}

    /**
     * @return array<int, array{recommendation: string, reason: string, average: ?float, subjects: array<string, float>, points: ?int}>
     */
    public function advise(SchoolClass $class, ?AcademicYear $year): array
    {
        $rule = PromotionRule::for($class->school_id, $class->curriculum());
        $terms = $this->terms($class, $year, $rule);
        $students = Student::where('school_class_id', $class->getKey())->where('status', 'active')->pluck('id');

        $advice = [];

        foreach ($students as $studentId) {
            $advice[$studentId] = $this->adviseStudent($studentId, $class, $rule, $terms);
        }

        return $advice;
    }

    /**
     * The terms whose results count: the whole year, or only its last term.
     *
     * @return Collection<int, Term>
     */
    protected function terms(SchoolClass $class, ?AcademicYear $year, PromotionRule $rule): Collection
    {
        $terms = Term::where('school_id', $class->school_id)
            ->when($year, fn ($q) => $q->where('academic_year_id', $year->getKey()))
            ->with('academicYear')
            ->get()
            ->sortBy(fn (Term $t) => $t->sortKey())
            ->values();

        return $rule->basis === 'final_term' ? $terms->take(-1)->values() : $terms;
    }

    protected function adviseStudent(int $studentId, SchoolClass $class, PromotionRule $rule, Collection $terms): array
    {
        $number = $class->number();

        if ($rule->auto_promote_upto && $number !== null && $number <= $rule->auto_promote_upto) {
            return $this->result('promote', 'Automatic progression', null);
        }

        // This student's results, term by term.
        $rows = $terms
            ->map(fn (Term $term) => $this->resultsFor($class, $term)->get($studentId))
            ->filter(fn ($row) => $row && $row['average'] !== null)
            ->values();

        if ($rows->isEmpty()) {
            return $this->result('no_results', 'No marks recorded this year', null);
        }

        $average = round($rows->avg('average'), 1);

        // Each subject's average across the terms.
        $subjectScores = [];
        foreach ($rows as $row) {
            foreach ($row['subjects'] as $result) {
                if ($result['final'] !== null) {
                    $subjectScores[$result['subject']->name][] = $result['final'];
                }
            }
        }
        $subjects = array_map(fn ($scores) => round(array_sum($scores) / count($scores), 1), $subjectScores);

        $points = $rows->last()['points'] ?? null;
        $min = (float) $rule->min_average;
        $problems = [];

        if ($min > 0 && $average < $min) {
            $problems[] = "Average {$average}% (below {$min}%)";
        }

        foreach ($rule->required_subjects ?? [] as $needed) {
            $match = collect($subjects)->first(fn ($score, $name) => stripos($name, $needed) !== false);
            $name = collect($subjects)->keys()->first(fn ($name) => stripos($name, $needed) !== false) ?? $needed;

            if ($match === null) {
                $problems[] = "No {$needed} mark";
            } elseif ($match < (float) $rule->subject_pass_mark) {
                $problems[] = "{$name} {$match}%";
            }
        }

        if ($rule->min_points && $points !== null && $points < $rule->min_points) {
            $problems[] = "{$points} points (below {$rule->min_points})";
        }

        $extra = ['subjects' => $subjects, 'points' => $points];

        if (! $problems) {
            return $this->result('promote', "Average {$average}%", $average, $extra);
        }

        // Clearly under the line: advised to repeat. Close to it, or only a
        // subject/points shortfall on a fair average: probation.
        $clearlyBelow = $min > 0 && $average < $min - (float) $rule->probation_margin;

        return $this->result($clearlyBelow ? 'repeat' : 'probation', implode(' · ', $problems), $average, $extra);
    }

    protected function resultsFor(SchoolClass $class, Term $term): Collection
    {
        return $this->termResults[$term->getKey()] ??= $this->calculator->forClass($class, $term)['rows']
            ->keyBy(fn ($row) => $row['student']->id);
    }

    protected function result(string $recommendation, string $reason, ?float $average, array $extra = []): array
    {
        return ['recommendation' => $recommendation, 'reason' => $reason, 'average' => $average, 'subjects' => $extra['subjects'] ?? [], 'points' => $extra['points'] ?? null];
    }

    public const LABELS = [
        'promote' => 'Promote',
        'probation' => 'Promote on probation',
        'repeat' => 'Advised to repeat',
        'no_results' => 'No results',
    ];
}
