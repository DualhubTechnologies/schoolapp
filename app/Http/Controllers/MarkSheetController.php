<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Mark;
use App\Models\MarkSheet;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Services\Academics\MarkSheets;
use App\Support\AcademicAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A mark sheet on paper: one exam, one class (or stream), one subject --
 * blank for marking scripts by hand, or filled in to check and sign.
 * Only for those who may enter that sheet's marks.
 */
class MarkSheetController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless(AcademicAccess::teaches(), 403);

        $schoolId = auth()->user()?->school_id;
        $assessment = Assessment::where('school_id', $schoolId)->with('term.academicYear')->findOrFail($request->integer('assessment'));
        $class = SchoolClass::where('school_id', $schoolId)->with(['classLevel', 'subjects'])->findOrFail($request->integer('class'));
        $subject = $class->subjects->firstWhere('id', $request->integer('subject'));

        abort_unless($subject && $assessment->appliesTo($class->curriculum()), 404);

        // null = every stream; a list = only these streams; [] = none.
        $streams = AcademicAccess::streamsForMarks($subject->pivot->teacher_id, $class->getKey());
        $sectionId = $request->integer('section') ?: null;

        abort_if($streams === [] || ($streams !== null && $sectionId !== null && ! in_array($sectionId, $streams, true)), 403);

        $students = Student::where('school_class_id', $class->getKey())
            ->where('status', 'active')
            ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
            ->when($streams !== null, fn ($q) => $q->whereIn('section_id', $streams))
            ->with(['combination.subjects', 'electives'])
            ->orderBy('name')
            ->get();

        $roster = MarkSheets::takers($students, $subject)['students'];
        $blank = $request->boolean('blank');

        $marks = $blank ? collect() : Mark::where('assessment_id', $assessment->getKey())
            ->where('subject_id', $subject->getKey())
            ->whereIn('student_id', $roster->pluck('id'))
            ->get()
            ->keyBy('student_id');

        return view('academics.mark-sheet', [
            'school' => $class->school,
            'assessment' => $assessment,
            'class' => $class,
            'section' => $sectionId ? Section::find($sectionId) : null,
            'subject' => $subject,
            'teacher' => $subject->pivot->teacher_id ? Staff::find($subject->pivot->teacher_id)?->name : null,
            'students' => $roster,
            'marks' => $marks,
            'blank' => $blank,
            'sheet' => MarkSheet::for($assessment, $class->getKey(), $subject->getKey()),
        ]);
    }
}
