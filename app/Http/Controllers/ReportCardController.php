<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\FeeStructure;
use App\Models\GradingScale;
use App\Models\Promotion;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Term;
use App\Services\Academics\PromotionAdvisor;
use App\Services\Academics\PromotionService;
use App\Services\Academics\ResultsCalculator;
use App\Support\AcademicAccess;
use Illuminate\Http\Request;
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
            AcademicAccess::manages() || in_array($request->integer('section'), AcademicAccess::classTeacherStreamsIn($class->getKey()), true),
            403,
        );

        // One exam only (a mid-term report), if it is one of this term's.
        $examId = $request->integer('exam') ?: null;
        abort_if($examId && ! Assessment::where('term_id', $term->getKey())->whereKey($examId)->exists(), 404);

        return $this->render($calculator, $class, $term, $request->integer('section') ?: null, $request->integer('student') ?: null, $request->boolean('fees'), $examId);
    }

    /**
     * The report cards themselves, once access has been checked: by the
     * school's staff above, or by the parent page for one learner.
     */
    public function render(ResultsCalculator $calculator, SchoolClass $class, Term $term, ?int $section, ?int $student, bool $showFees, ?int $examId = null): View
    {
        $results = $calculator->forClass($class, $term, $section, $examId);
        $rows = $results['rows']->filter(fn ($r) => $r['average'] !== null);

        if ($student) {
            $rows = $rows->filter(fn ($r) => $r['student']->id === $student);
        }

        abort_if($rows->isEmpty(), 404, 'No results to print.');

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

        if ($isFinalTerm && ! $examId && ! $promotions->isFinalClass($class)) {
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

        $scales = GradingScale::where('school_id', $class->school_id)
            ->where('curriculum', $class->curriculum())
            ->with('bands')
            ->get()
            ->keyBy('purpose');

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
        ]);
    }
}
