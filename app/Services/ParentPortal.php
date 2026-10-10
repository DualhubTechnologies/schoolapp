<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\Mark;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Services\Attendance\AttendanceSummary;
use Illuminate\Support\Collection;

/**
 * What a parent sees: their own children only (every guardian record
 * linked to their login), with fees, receipts, report cards the school
 * has released and attendance. Shared by the parent's home screen
 * (App\Filament\App\Widgets\ParentChildren) and the SMS link page
 * (App\Http\Controllers\ParentPageController).
 */
class ParentPortal
{
    public function __construct(
        protected StudentLedger $ledger,
        protected AttendanceSummary $attendance,
    ) {}

    /**
     * The parent's children who are at school now.
     *
     * @return Collection<int, Student>
     */
    public function children(User $parent): Collection
    {
        return Student::query()
            ->whereIn('guardian_id', Guardian::where('user_id', $parent->getKey())->select('id'))
            ->where('status', 'active')
            ->with(['school', 'schoolClass', 'section'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Everything shown for one child.
     *
     * @return array<string, mixed> student, term, balance, summary, payments, reports, attendance, link
     */
    public function overview(Student $student): array
    {
        $term = Term::current($student->school_id);
        $attendance = $term?->start_date
            ? ($this->attendance->forStudents([$student->getKey()], $term->start_date, today())[$student->getKey()] ?? null)
            : null;

        return [
            'student' => $student,
            'term' => $term,
            'balance' => $student->balance(),
            'summary' => $term ? $this->ledger->termSummary($student, $term) : null,
            'payments' => $student->payments()->latest('paid_on')->latest('id')->limit(5)->get(),
            'reports' => $this->releasedReportTerms($student, $term),
            'attendance' => $attendance,
            'link' => $student->parentPageUrl(),
        ];
    }

    /**
     * Released terms of this academic year in which the learner has marks.
     * Earlier years are left out: the learner has since changed class, and
     * the school keeps those report cards.
     *
     * @return Collection<int, Term>
     */
    public function releasedReportTerms(Student $student, ?Term $current): Collection
    {
        if (! $current) {
            return collect();
        }

        return Term::where('school_id', $student->school_id)
            ->where('academic_year_id', $current->academic_year_id)
            ->whereNotNull('report_cards_released_at')
            ->whereIn('id', Mark::where('student_id', $student->getKey())
                ->join('assessments', 'assessments.id', '=', 'marks.assessment_id')
                ->select('assessments.term_id'))
            ->with('academicYear')
            ->orderByDesc('sequence')
            ->get();
    }
}
