<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\DocumentVerification;
use App\Models\FeeStructure;
use App\Models\GradingScale;
use App\Models\Mark;
use App\Models\Promotion;
use App\Models\ReportCardTemplate;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Term;
use App\Services\Academics\PromotionAdvisor;
use App\Services\Academics\PromotionService;
use App\Services\Academics\ResultsCalculator;
use App\Services\Academics\TopicAssessment;
use App\Services\Attendance\AttendanceSummary;
use App\Support\AcademicAccess;
use App\Support\Edition;
use App\Support\QrImage;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Printable report cards for a class (or one student), one A4 page each.
 */
class ReportCardController extends Controller
{
    public function __invoke(Request $request, ResultsCalculator $calculator): View
    {
        abort_unless(AcademicAccess::teaches(), 403);

        $schoolId = auth()->user()->school_id;
        $class = SchoolClass::where('school_id', $schoolId)->with('classLevel')->findOrFail($request->integer('class'));
        $term = Term::where('school_id', $schoolId)->with('academicYear')->findOrFail($request->integer('term'));

        // Teachers print only the stream they are class teacher of.
        abort_unless(
            AcademicAccess::manages() || AcademicAccess::isClassTeacherOf($class->getKey(), $request->integer('section') ?: null),
            403,
        );

        // One exam only (a mid-term report), if it is one of this term's.
        $examId = $request->integer('exam') ?: null;
        abort_if($examId && ! Assessment::where('term_id', $term->getKey())->whereKey($examId)->exists(), 404);

        // ?students=1,2,3: just the learners the Report Cards page is showing
        // after a search or filter.
        $only = array_values(array_filter(array_map('intval', explode(',', (string) $request->query('students')))));

        return $this->render($calculator, $class, $term, $request->integer('section') ?: null, $request->integer('student') ?: null, $request->boolean('fees'), $only, $examId);
    }

    /**
     * The report cards themselves, once access has been checked: by the
     * school's staff above, or by the parent page for one learner.
     *
     * @param  list<int>  $only  when given, just these learners
     * @param  int|null  $examId  one exam only (a mid-term report)
     */
    public function render(ResultsCalculator $calculator, SchoolClass $class, Term $term, ?int $section, ?int $student, bool $showFees, array $only = [], ?int $examId = null): View
    {
        $results = $calculator->forClass($class, $term, $section, $examId);
        $rows = $results['rows']->filter(fn ($r) => $r['average'] !== null);

        if ($student) {
            $rows = $rows->filter(fn ($r) => $r['student']->id === $student);
        }

        if ($only !== []) {
            $rows = $rows->filter(fn ($r) => in_array($r['student']->id, $only, true));
        }

        abort_if($rows->isEmpty(), 404, 'No results to print.');

        // The school's template decides what is printed. Fees are the one
        // part chosen per print: the Report Cards page ticks the box from
        // the template, and the parent page never shows them.
        $template = ReportCardTemplate::forSchool($class->school_id);

        $nextTerm = $term->next();

        // Next term's fees per residency, from the fee set-up in force then.
        $nextFees = $showFees && $nextTerm
            ? FeeStructure::termlyFor($nextTerm)->where('school_class_id', $class->getKey())
            : collect();

        $teachers = Staff::whereIn('id', $class->subjects->pluck('pivot.teacher_id')->filter())->get()
            ->mapWithKeys(fn (Staff $s) => [$s->id => $s->reportInitials()]);

        // The last term of the year carries the promotion decision, as
        // Ugandan report cards do: the decision already made if the class
        // has been promoted, otherwise the rules' recommendation.
        $promotionText = [];
        $isFinalTerm = ! $nextTerm || $nextTerm->academic_year_id !== $term->academic_year_id;
        $promotions = app(PromotionService::class);

        if ($template->shows('promotion') && $isFinalTerm && ! $examId && ! $promotions->isFinalClass($class)) {
            $nextClass = $promotions->nextClass($class)?->name ?? 'the next class';
            $made = Promotion::where('academic_year_id', $term->academic_year_id)
                ->whereIn('student_id', $rows->pluck('student.id'))
                ->whereNull('reversed_at')
                ->with('toClass')
                ->get()
                ->keyBy('student_id');
            $advice = app(PromotionAdvisor::class)->advise($class, $term->academicYear);

            foreach ($rows as $row) {
                $id = $row['student']->id;
                $decision = $made->get($id)?->action ?? ($advice[$id]['recommendation'] ?? null);
                $to = $made->get($id)?->toClass?->name ?? $nextClass;

                $promotionText[$id] = match ($decision) {
                    'promote' => "Promoted to {$to}",
                    'probation' => "Promoted to {$to} on probation",
                    'repeat' => "Advised to repeat {$class->name}",
                    default => null,
                };
            }
        }

        // Days present this term, from the class register (a number typed
        // on the term report, if any, takes precedence in the view).
        $attendance = $term->start_date
            ? app(AttendanceSummary::class)->forStudents($rows->pluck('student.id')->all(), $term->start_date, $term->end_date)
            : [];

        $scales = GradingScale::where('school_id', $class->school_id)
            ->where('curriculum', $class->curriculum())
            ->with('bands')
            ->get()
            ->keyBy('purpose');

        // Project work gets its own section: one line per project mark.
        $projects = $template->shows('projects') && ! $examId
            ? $this->projects($results['assessments'], $rows->pluck('student.id')->all(), $class, $scales->get($class->curriculum() === 'a_level' ? 'paper' : 'subject'), $teachers->all())
            : [];

        // Each term's average so far this year, for the trend table.
        $termAverages = $template->shows('term_trend') && ! $examId
            ? $this->termAverages($calculator, $class, $term, $results)
            : [];

        // NCDC topic levels (O-Level), when teachers have recorded them.
        // Primary: each exam graded on its own, as Ugandan primary report
        // cards are: the mark and grade per subject, then the exam's
        // total, aggregate and division.
        $examResults = $class->curriculum() === 'primary' && ! $examId
            ? $this->examResults($calculator, $class, $term, $section, $results['assessments'])
            : [];

        // A QR code on each card that anyone can scan to check it is
        // genuine. Online only: the Windows app's cards are not on the web.
        $verifications = $template->shows('verification') && ! Edition::isDesktop()
            ? $this->verifications($rows, $class, $term, $results, $examResults)
            : [];

        $topicScores = $class->curriculum() === 'o_level' && $template->shows('topics') && ! $examId
            ? app(TopicAssessment::class)->forReport($term, $rows->pluck('student.id')->all())
            : [];

        return view('academics.report-card', [
            'results' => $results,
            'rows' => $rows,
            'school' => $class->school,
            'class' => $class,
            'term' => $term,
            'nextTerm' => $nextTerm,
            'showFees' => $showFees,
            'nextFees' => $nextFees,
            'teachers' => $teachers,
            'scales' => $scales,
            'promotionText' => $promotionText,
            'attendance' => $attendance,
            'template' => $template,
            'topicScores' => $topicScores,
            'projects' => $projects,
            'termAverages' => $termAverages,
            'examResults' => $examResults,
            'verifications' => $verifications,
        ]);
    }

    /**
     * Project work marks per learner: the project (the exam's name), the
     * subject it was marked under, score, grade, remark and teacher.
     *
     * @param  Collection<int, Assessment>  $assessments
     * @param  array<int, mixed>  $studentIds
     * @param  array<array-key, mixed>  $teachers  staff id => initials
     * @return array<int, list<array{title: string, subject: string, score: float, max: float, percent: float, grade: string|null, remark: string|null, teacher: string|null}>>
     */
    protected function projects(Collection $assessments, array $studentIds, SchoolClass $class, ?GradingScale $scale, array $teachers): array
    {
        $projectExams = $assessments->filter(fn (Assessment $a): bool => $a->type === 'project')->keyBy('id');

        if ($projectExams->isEmpty()) {
            return [];
        }

        $subjects = $class->subjects->keyBy('id');
        $out = [];

        $marks = Mark::whereIn('assessment_id', $projectExams->keys())
            ->whereIn('student_id', $studentIds)
            ->whereNotNull('score')
            ->orderBy('assessment_id')
            ->get();

        foreach ($marks as $mark) {
            $exam = $projectExams->get($mark->assessment_id);
            $subject = $subjects->get($mark->subject_id);

            if (! $exam || (float) $exam->max_score <= 0) {
                continue;
            }

            $percent = round(min(100, (float) $mark->score / (float) $exam->max_score * 100), 1);
            $band = $scale?->bandFor($percent);

            $out[$mark->student_id][] = [
                'title' => $exam->name,
                'subject' => $subject->name ?? '',
                'score' => (float) $mark->score,
                'max' => (float) $exam->max_score,
                'percent' => $percent,
                'grade' => $band?->grade,
                'remark' => $mark->comment ?: $band?->descriptor,
                'teacher' => is_string($initials = $teachers[$subject?->pivot->teacher_id ?? 0] ?? null) ? $initials : null,
            ];
        }

        return $out;
    }

    /**
     * Every learner's average in each term of the year up to this one:
     * student id => term label => average.
     *
     * @param  array<string, mixed>  $current  this term's results, already worked out
     * @return array<int, array<string, float|null>>
     */
    protected function termAverages(ResultsCalculator $calculator, SchoolClass $class, Term $term, array $current): array
    {
        $terms = Term::where('academic_year_id', $term->academic_year_id)
            ->with('academicYear')
            ->orderBy('sequence')
            ->get()
            ->filter(fn (Term $t): bool => $t->sequence <= $term->sequence);

        if ($terms->count() < 2) {
            return [];
        }

        $out = [];

        foreach ($terms as $t) {
            $rows = $t->is($term) ? $current['rows'] : $calculator->forClass($class, $t)['rows'];

            foreach ($rows as $row) {
                $out[$row['student']->id][$t->name] = $row['average'];
            }
        }

        return $out;
    }

    /**
     * Each exam's results on their own, for primary report cards: one
     * entry per exam that has marks: the exam, and its rows keyed by
     * student id.
     *
     * @param  Collection<int, Assessment>  $assessments
     * @return list<array<string, mixed>>
     */
    protected function examResults(ResultsCalculator $calculator, SchoolClass $class, Term $term, ?int $section, Collection $assessments): array
    {
        $out = [];

        foreach ($assessments as $assessment) {
            if (in_array($assessment->type, ['project', 'topics'], true)) {
                continue;
            }

            $rows = $calculator->forClass($class, $term, $section, $assessment->getKey())['rows']
                ->filter(fn (array $row): bool => $row['average'] !== null)
                ->keyBy(fn (array $row): int => $row['student']->id);

            if ($rows->isNotEmpty()) {
                $out[] = ['exam' => $assessment, 'rows' => $rows];
            }
        }

        return $out;
    }

    /**
     * Each learner's verification code and QR image. The check page shows
     * the card's headline result as printed; reprinting a card whose
     * result changed gives it a new code and marks the old one replaced.
     *
     * @param  Collection<int, mixed>  $rows
     * @param  array<string, mixed>  $results
     * @param  list<array<string, mixed>>  $examResults
     * @return array<int, array{code: string, url: string, qr: string}>
     */
    protected function verifications(Collection $rows, SchoolClass $class, Term $term, array $results, array $examResults): array
    {
        $exam = $results['exam'] instanceof Assessment ? $results['exam'] : null;
        $n = fn ($value): string => $value === null ? '—' : rtrim(rtrim(number_format((float) $value, 1), '0'), '.');
        $schoolName = (string) $class->school()->value('name');
        $out = [];

        foreach ($rows as $row) {
            $student = $row['student'];

            // Primary: the last exam's result, as the card's summary box shows it.
            $overall = $row;
            $resultFrom = null;
            foreach ($examResults as $examResult) {
                if (isset($examResult['rows'][$student->id])) {
                    $overall = $examResult['rows'][$student->id];
                    $resultFrom = $examResult['exam']->name;
                }
            }

            $result = match ($results['curriculum']) {
                'primary' => array_filter([
                    'Result from' => $resultFrom,
                    'Aggregate' => (string) ($overall['aggregate'] ?? 'X'),
                    'Division' => ($overall['division'] ?? null) === 'X' ? 'Incomplete' : (string) ($overall['division'] ?? '—'),
                    'Average' => $n($overall['average']).'%',
                ]),
                'a_level' => [
                    'Total points' => ($row['points'] ?? '—').' out of 20',
                    'Principal grades' => (string) (($row['principal_grades'] ?? '') ?: '—'),
                    'Average' => $n($row['average']).'%',
                ],
                'nursery' => [
                    'Overall' => (string) ($row['overall_grade'] ?? '—'),
                    'Average' => $n($row['average']).'%',
                ],
                default => [
                    'Average' => $n($row['average']).'%',
                    'Total marks' => $n($row['total']),
                ],
            };

            $document = DocumentVerification::issue(
                $class->school_id,
                'report_card',
                "report_card:{$student->id}:{$term->id}:".($exam->id ?? 'term'),
                [
                    'school' => $schoolName,
                    'learner' => (string) $student->name,
                    'admission_no' => (string) $student->admission_no,
                    'class' => $class->name.($student->section ? ' · '.$student->section->name : ''),
                    'term' => $term->label(),
                    'exam' => $exam?->name,
                    'result' => $result,
                ],
            );

            $out[(int) $student->id] = ['code' => $document->code, 'url' => $document->url(), 'qr' => QrImage::svg($document->url())];
        }

        return $out;
    }
}
