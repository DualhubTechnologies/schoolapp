<?php

namespace App\Http\Controllers;

use App\Models\DocumentVerification;
use App\Models\Student;
use App\Models\StudentPayment;
use App\Services\FeeReminderService;
use App\Services\Transport\TransportLedger;
use App\Support\Edition;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Printable fee documents: payment receipts and reminder letters. Each is
 * a plain page sized for paper that opens the print dialog by itself.
 */
class FeeDocumentController extends Controller
{
    public function receipt(int $payment, TransportLedger $ledger): View
    {
        // Voided receipts can still be viewed (and are stamped VOID).
        $payment = StudentPayment::withVoided()
            ->with(['student.schoolClass', 'student.section', 'student.guardian', 'school', 'term.academicYear'])
            ->findOrFail($payment);

        $this->authorizeSchool($payment->school_id);

        // For van users, how this payment was split: transport is paid first.
        $transport = $payment->student ? $ledger->forStudent($payment->student) : null;

        return view('fees.receipt', [
            'payment' => $payment,
            'student' => $payment->student,
            'school' => $payment->school,
            'balanceAfter' => $payment->student?->balance() ?? 0,
            // A QR code anyone can scan to check the receipt is genuine (online only).
            'verification' => Edition::isDesktop() ? null : DocumentVerification::forReceipt($payment),
            'transportShare' => $transport && $transport['charged'] > 0 && $payment instanceof StudentPayment && ! $payment->isVoided()
                ? ($transport['payments'][$payment->getKey()] ?? 0.0)
                : null,
        ]);
    }

    public function letters(Request $request, FeeReminderService $reminders): View
    {
        $ids = collect(explode(',', (string) $request->query('students')))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique();

        $students = Student::query()
            ->whereKey($ids)
            ->where('school_id', auth()->user()->school_id)
            ->orderBy('name')
            ->get();

        abort_if($students->isEmpty(), 404);

        return view('fees.reminder-letters', [
            'letters' => $reminders->letters($students, $request->query('deadline')),
            'school' => auth()->user()->school,
        ]);
    }

    protected function authorizeSchool(int $schoolId): void
    {
        $user = auth()->user();

        abort_unless($user && ($user->hasRole('Super Admin') || $user->school_id === $schoolId), 403);
    }
}
