<?php

namespace App\Services;

use App\Concerns\EmbedsImages;
use App\Models\Student;
use App\Models\Term;
use App\Support\PrivateFiles;
use Barryvdh\DomPDF\Facade\Pdf;

class StudentDocumentService
{
    use EmbedsImages;

    /**
     * Generate the admission letter PDF for a student and return the
     * Dompdf instance (caller decides stream/download). Fees shown are
     * whatever applies to this student for the school's current term
     * (BillingService — the same rules a real billing run would use), so
     * there is nothing to type in before printing.
     */
    public function admissionLetter(Student $student, BillingService $billing)
    {
        $student->loadMissing(['school', 'guardian', 'schoolClass', 'section', 'residencyType']);

        $term = Term::current($student->school_id);
        $feeLines = $term ? $billing->applicableFees($student, $term) : collect();
        $feeTotal = $feeLines->sum(fn ($fee) => (float) $fee->amount);

        $pdf = Pdf::loadView('pdf.admission-letter', [
            'student' => $student,
            'school' => $student->school,
            'term' => $term,
            'feeLines' => $feeLines,
            'feeTotal' => $feeTotal,
            'logoPath' => $this->embeddableImage($student->school->logo ?? null),
            'signaturePath' => PrivateFiles::dataUri($student->school->hm_signature ?? null),
        ]);

        $pdf->setPaper('a4');

        return $pdf;
    }

    /**
     * Generate the student profile PDF and return the Dompdf instance: the
     * learner's full record on one A4 page, with the fees position and
     * space for signatures and the school stamp.
     */
    public function profile(Student $student)
    {
        $student->loadMissing(['school', 'guardian', 'schoolClass', 'section', 'residencyType', 'house', 'combination', 'electives']);

        $pdf = Pdf::loadView('pdf.student-profile', [
            'student' => $student,
            'school' => $student->school,
            'term' => Term::current($student->school_id),
            'balance' => $student->balance(),
            'totalPaid' => $student->totalPaid(),
            'logoPath' => $this->embeddableImage($student->school->logo ?? null),
            'photoPath' => PrivateFiles::dataUri($student->photo),
            'signaturePath' => PrivateFiles::dataUri($student->school->hm_signature ?? null),
        ]);

        $pdf->setPaper('a4');

        return $pdf;
    }
}
