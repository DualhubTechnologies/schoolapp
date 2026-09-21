<?php

namespace App\Http\Controllers;

use App\Models\FeeStructure;
use App\Models\GradingScale;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Term;
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

        $results = $calculator->forClass($class, $term, $request->integer('section') ?: null);
        $rows = $results['rows']->filter(fn ($r) => $r['average'] !== null);

        if ($student = $request->integer('student')) {
            $rows = $rows->filter(fn ($r) => $r['student']->id === $student);
        }

        abort_if($rows->isEmpty(), 404, 'No results to print.');

        $nextTerm = $term->next();
        $showFees = $request->boolean('fees');

        // Next term's fees per residency, from the fee set-up in force then.
        $nextFees = $showFees && $nextTerm
            ? FeeStructure::termlyFor($nextTerm)->where('school_class_id', $class->getKey())
            : collect();

        $teachers = Staff::whereIn('id', $class->subjects->pluck('pivot.teacher_id')->filter())->pluck('name', 'id');

        $scales = GradingScale::where('school_id', $schoolId)
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
        ]);
    }
}
