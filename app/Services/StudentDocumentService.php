<?php

namespace App\Services;

use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class StudentDocumentService
{
    /**
     * Generate the admission letter PDF for a student and return the
     * Dompdf instance (caller decides stream/download).
     *
     * @param  string|null  $term  e.g. 'Term 1' — which term's fees to show
     * @param  string|null  $academicYear  e.g. '2026'
     * @param  string|null  $openingDate  ISO date string for term opening
     */
    public function admissionLetter(
        Student $student,
        ?string $term = null,
        ?string $academicYear = null,
        ?string $openingDate = null,
    ) {
        $student->loadMissing(['school', 'guardian', 'schoolClass', 'section']);

        $academicYear ??= (string) now()->year;
        $feeStructure = $student->feeStructure($term, $academicYear);

        $pdf = Pdf::loadView('pdf.admission-letter', [
            'student' => $student,
            'school' => $student->school,
            'feeStructure' => $feeStructure,
            'academicYear' => $academicYear,
            'openingDate' => $openingDate,
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

            return 'data:' . $mime . ';base64,' . base64_encode($contents);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
