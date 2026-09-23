<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Term;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class StudentDocumentService
{
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
        ]);

        $pdf->setPaper('a4');

        return $pdf;
    }

    /**
     * Generate the student profile PDF and return the Dompdf instance.
     */
    public function profile(Student $student)
    {
        $student->loadMissing(['school', 'guardian', 'schoolClass', 'section']);

        $pdf = Pdf::loadView('pdf.student-profile', [
            'student' => $student,
            'school' => $student->school,
            'logoPath' => $this->embeddableImage($student->school->logo ?? null),
            'photoPath' => $this->embeddableImage($student->photo),
        ]);

        $pdf->setPaper('a4');

        return $pdf;
    }

    /**
     * Convert a stored (public disk) image path into a base64 data URI
     * that dompdf can embed. Returns null if missing/unreadable.
     *
     * dompdf cannot fetch web URLs reliably and http fetching is disabled
     * by default for security, so we embed the raw bytes instead.
     */
    protected function embeddableImage(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        // Photos/logos are stored on the 'public' disk (FileUpload default).
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        try {
            $contents = Storage::disk('public')->get($path);
            $mime = Storage::disk('public')->mimeType($path) ?: 'image/jpeg';

            return 'data:'.$mime.';base64,'.base64_encode($contents);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
