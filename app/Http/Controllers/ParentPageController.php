<?php

namespace App\Http\Controllers;

use App\Models\DocumentVerification;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentPayment;
use App\Models\Term;
use App\Services\Academics\ResultsCalculator;
use App\Services\ParentPortal;
use App\Services\StudentLedger;
use App\Services\Transport\TransportLedger;
use App\Support\Edition;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The parent page: what a parent sees from the short link in an SMS, with
 * no login. One learner only: the fees balance, this term's statement,
 * receipts, and report cards the school has released.
 *
 * The link's token is the only key, so it is long enough not to be
 * guessed, the routes are rate-limited, and nothing here can be changed.
 */
class ParentPageController extends Controller
{
    public function show(string $token, StudentLedger $ledger): View
    {
        $student = $this->student($token);
        $term = Term::current($student->school_id);

        return view('parents.page', [
            'student' => $student,
            'school' => $student->school,
            'term' => $term,
            'balance' => $student->balance(),
            'termSummary' => $term ? $ledger->termSummary($student, $term) : null,
            'entries' => $term ? $ledger->entries($student, $term) : collect(),
            'payments' => $student->payments()->latest('paid_on')->latest('id')->limit(10)->get(),
            'reports' => $this->releasedReportTerms($student, $term),
            'token' => $token,
        ]);
    }

    public function receipt(string $token, int $payment, TransportLedger $transport): View
    {
        $student = $this->student($token);

        $payment = StudentPayment::with(['student.schoolClass', 'student.section', 'student.guardian', 'school', 'term.academicYear'])
            ->where('student_id', $student->getKey())
            ->findOrFail($payment);

        $split = $transport->forStudent($student);

        return view('fees.receipt', [
            'payment' => $payment,
            'student' => $student,
            'school' => $student->school,
            'balanceAfter' => $student->balance(),
            // A QR code anyone can scan to check the receipt is genuine (online only).
            'verification' => Edition::isDesktop() ? null : DocumentVerification::forReceipt($payment),
            'transportShare' => $split['charged'] > 0 ? ($split['payments'][$payment->getKey()] ?? 0.0) : null,
        ]);
    }

    public function reportCard(string $token, int $term, ResultsCalculator $calculator, ReportCardController $reports): View
    {
        $student = $this->student($token);

        $term = $this->releasedReportTerms($student, Term::current($student->school_id))
            ->firstWhere('id', $term) ?? abort(404);

        $class = SchoolClass::where('school_id', $student->school_id)
            ->with('classLevel')
            ->findOrFail($student->school_class_id);

        return $reports->render($calculator, $class, $term, null, $student->getKey(), false);
    }

    /**
     * @return Collection<int, Term>
     */
    protected function releasedReportTerms(Student $student, ?Term $current): Collection
    {
        return app(ParentPortal::class)->releasedReportTerms($student, $current);
    }

    protected function student(string $token): Student
    {
        abort_unless(strlen($token) >= 10, 404);

        return Student::with(['school', 'schoolClass', 'section', 'guardian'])
            ->where('parent_token', $token)
            ->firstOrFail();
    }
}
