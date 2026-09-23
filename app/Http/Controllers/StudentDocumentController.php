<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\BillingService;
use App\Services\StudentDocumentService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Printable student documents. Opens inline in the browser's own PDF
 * viewer (which has its own print button), behind the panel's login.
 */
class StudentDocumentController extends Controller
{
    public function admissionLetter(Student $student, StudentDocumentService $documents, BillingService $billing): Response
    {
        $this->authorizeSchool($student->school_id);

        $pdf = $documents->admissionLetter($student, $billing);

        return $pdf->stream('admission-letter-'.$student->admission_no.'.pdf');
    }

    public function profile(Student $student, StudentDocumentService $documents): Response
    {
        $this->authorizeSchool($student->school_id);

        $pdf = $documents->profile($student);

        return $pdf->stream('profile-'.$student->admission_no.'.pdf');
    }

    protected function authorizeSchool(int $schoolId): void
    {
        $user = auth()->user();

        abort_unless($user && ($user->hasRole('Super Admin') || $user->school_id === $schoolId), 403);
    }
}
