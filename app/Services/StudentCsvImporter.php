<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StudentCsvImporter
{
    /**
     * Required CSV columns (header must contain these).
     */
    public const REQUIRED_COLUMNS = [
        'name',
        'admission_no',
        'class',
    ];

    /**
     * All recognised columns.
     */
    public const VALID_COLUMNS = [
        'name',
        'admission_no',
        'lin',
        'nin',
        'class',
        'section',
        'gender',
        'date_of_birth',
        'admission_date',
        'phone',
        'email',
        'address',
        'medical_notes',
        'guardian_name',
        'guardian_phone',
        'guardian_email',
        'guardian_relationship',
        'status',
    ];

    protected StudentImport $import;
    protected int $schoolId;
    protected array $classCache = [];
    protected array $sectionCache = [];
    protected array $existingAdmissionNos = [];

    /**
     * When true, imported students are marked as CONFIRMED full students
     * (used for the school's existing/continuing student body). When false,
     * students come in PROVISIONAL — the default for new admissions.
     */
    protected bool $confirmOnImport = false;

    public function __construct(StudentImport $import)
    {
        $this->import = $import;
        $this->schoolId = $import->school_id;

        // The confirm-existing choice is stored on the import record so it
        // survives the queued job (which reconstructs the importer fresh).
        $this->confirmOnImport = (bool) ($import->confirm_on_import ?? false);
    }

    /**
     * Build a failure result that matches the full validation shape,
     * so the front-end preview step can always render it.
     */
    protected function failValidation(string $message, string $field = 'file'): array
    {
        return [
            'valid' => false,
            'total_rows' => 0,
            'error_count' => 1,
            'duplicate_count' => 0,
            'errors' => [['row' => 0, 'field' => $field, 'message' => $message]],
            'preview' => [],
            'unknown_columns' => [],
        ];
    }

    /**
     * Phase 1: Validate the CSV file without importing anything.
     * Returns ['valid' => bool, 'errors' => [...], 'preview' => [...]]
     */
    public function validate(): array
    {
        $this->import->update(['status' => 'validating']);
        $this->import->appendLog('Reading CSV file...');

        $path = Storage::disk('local')->path($this->import->file_path);

        if (! file_exists($path)) {
            $this->import->update([
                'status' => 'failed',
                'error_message' => 'CSV file not found on disk.',
            ]);
            return $this->failValidation('File not found.');
        }

        $handle = fopen($path, 'r');
        if (! $handle) {
            $this->import->update([
                'status' => 'failed',
                'error_message' => 'Could not open CSV file.',
            ]);
            return $this->failValidation('Cannot open file.');
        }

        // Read header row
        $rawHeader = fgetcsv($handle);
        if (! $rawHeader) {
            fclose($handle);
            $this->import->update([
                'status' => 'failed',
                'error_message' => 'CSV file is empty or has no header row.',
            ]);
            return $this->failValidation('Empty file or missing header.', 'header');
        }

        // Normalise headers: lowercase, trim, underscores. Strip UTF-8 BOM if present.
        $headers = array_map(function ($h) {
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
            return str_replace([' ', '-'], '_', strtolower(trim($h)));
        }, $rawHeader);

        $this->import->appendLog('Checking required columns...');

        // Check required columns
        $missing = array_diff(self::REQUIRED_COLUMNS, $headers);
        if (! empty($missing)) {
            $msg = 'Missing required columns: ' . implode(', ', $missing);
            $this->import->update([
                'status' => 'failed',
                'error_message' => $msg,
            ]);
            fclose($handle);
            return $this->failValidation($msg, 'header');
        }

        // Check for unrecognised columns (warn, don't fail)
        $unknown = array_diff($headers, self::VALID_COLUMNS);

        // Pre-load caches
        $this->loadCaches();

        $this->import->appendLog('Validating records...');

        $errors = [];
        $preview = [];
        $rowNum = 1; // header was row 0
        $totalRows = 0;
        $seenAdmissionNos = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            $totalRows++;

            // Skip completely empty rows
            if (count(array_filter($row, fn ($v) => trim($v ?? '') !== '')) === 0) {
                $totalRows--;
                continue;
            }

            // Map columns to values
            $data = [];
            foreach ($headers as $i => $col) {
                $data[$col] = trim($row[$i] ?? '');
            }

            $rowErrors = $this->validateRow($data, $rowNum, $seenAdmissionNos);

            if (! empty($rowErrors)) {
                foreach ($rowErrors as $err) {
                    $errors[] = $err;
                }
            }

            // Track admission numbers for in-file duplicate detection
            $admNo = $data['admission_no'] ?? '';
            if ($admNo !== '') {
                $seenAdmissionNos[$admNo] = ($seenAdmissionNos[$admNo] ?? 0) + 1;
            }

            // Keep first 5 rows for preview
            if (count($preview) < 5) {
                $preview[] = $data;
            }
        }

        fclose($handle);

        // Detect in-file duplicates
        $duplicateCount = 0;
        foreach ($seenAdmissionNos as $admNo => $count) {
            if ($count > 1) {
                $duplicateCount += $count - 1;
                $errors[] = [
                    'row' => 0,
                    'field' => 'admission_no',
                    'message' => "Admission number '{$admNo}' appears {$count} times in the file.",
                ];
            }
        }

        $this->import->update([
            'status' => 'validated',
            'total_rows' => $totalRows,
            'failed_rows' => count(array_unique(array_column($errors, 'row'))),
            'duplicate_rows' => $duplicateCount,
            'validation_errors' => $errors,
        ]);

        $this->import->appendLog("Validation complete. {$totalRows} rows scanned, " . count($errors) . " issues found.");

        return [
            'valid' => empty($errors),
            'total_rows' => $totalRows,
            'error_count' => count($errors),
            'duplicate_count' => $duplicateCount,
            'errors' => array_slice($errors, 0, 100), // Cap at 100 for display
            'preview' => $preview,
            'unknown_columns' => $unknown,
        ];
    }

    /**
     * Phase 2: Actually import the validated rows.
     */
    public function import(): void
    {
        $this->import->update([
            'status' => 'importing',
            'started_at' => now(),
            'processed_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
        ]);

        $this->import->appendLog(
            $this->confirmOnImport
                ? 'Starting import (existing students — will be marked confirmed)...'
                : 'Starting import (new admissions — will be marked provisional)...'
        );

        $path = Storage::disk('local')->path($this->import->file_path);
        $handle = fopen($path, 'r');

        // Read and normalise headers
        $rawHeader = fgetcsv($handle);
        $headers = array_map(function ($h) {
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
            return str_replace([' ', '-'], '_', strtolower(trim($h)));
        }, $rawHeader);

        $this->loadCaches();

        $rowNum = 0;
        $successCount = 0;
        $failCount = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;

            // Skip empty rows
            if (count(array_filter($row, fn ($v) => trim($v ?? '') !== '')) === 0) {
                continue;
            }

            $data = [];
            foreach ($headers as $i => $col) {
                $data[$col] = trim($row[$i] ?? '');
            }

            try {
                $this->importRow($data);
                $successCount++;
            } catch (\Throwable $e) {
                $failCount++;
                $errors[] = [
                    'row' => $rowNum + 1, // +1 for header
                    'field' => 'import',
                    'message' => $e->getMessage(),
                ];
                Log::warning("Student import row {$rowNum} failed", [
                    'import_id' => $this->import->id,
                    'data' => $data,
                    'error' => $e->getMessage(),
                ]);
            }

            // Update progress every 10 rows to avoid hammering the DB
            if ($rowNum % 10 === 0) {
                $this->import->update([
                    'processed_rows' => $rowNum,
                    'successful_rows' => $successCount,
                    'failed_rows' => $failCount,
                ]);
            }
        }

        fclose($handle);

        $this->import->update([
            'status' => 'completed',
            'processed_rows' => $rowNum,
            'successful_rows' => $successCount,
            'failed_rows' => $failCount,
            'validation_errors' => $errors ?: null,
            'completed_at' => now(),
        ]);

        $duration = $this->import->started_at->diffInSeconds($this->import->completed_at);
        $this->import->appendLog("Import completed. {$successCount} students imported, {$failCount} failed. Duration: {$duration}s.");
    }

    /**
     * Validate a single CSV row.
     */
    protected function validateRow(array $data, int $rowNum, array $seenAdmissionNos): array
    {
        $errors = [];

        // Required fields
        if (empty($data['name'])) {
            $errors[] = ['row' => $rowNum, 'field' => 'name', 'message' => "Row {$rowNum}: Name is required."];
        }

        if (empty($data['admission_no'])) {
            $errors[] = ['row' => $rowNum, 'field' => 'admission_no', 'message' => "Row {$rowNum}: Admission number is required."];
        } elseif (isset($this->existingAdmissionNos[$data['admission_no']])) {
            $errors[] = ['row' => $rowNum, 'field' => 'admission_no', 'message' => "Row {$rowNum}: Admission number '{$data['admission_no']}' already exists in the database."];
        }

        // Class must exist
        if (empty($data['class'])) {
            $errors[] = ['row' => $rowNum, 'field' => 'class', 'message' => "Row {$rowNum}: Class is required."];
        } elseif (! isset($this->classCache[$data['class']])) {
            $errors[] = ['row' => $rowNum, 'field' => 'class', 'message' => "Row {$rowNum}: Class '{$data['class']}' not found. Create it first."];
        }

        // Section must exist under the class (if provided)
        if (! empty($data['section']) && ! empty($data['class'])) {
            $classId = $this->classCache[$data['class']] ?? null;
            $key = $classId . ':' . $data['section'];
            if ($classId && ! isset($this->sectionCache[$key])) {
                $errors[] = ['row' => $rowNum, 'field' => 'section', 'message' => "Row {$rowNum}: Section '{$data['section']}' not found under class '{$data['class']}'."];
            }
        }

        // Gender
        if (! empty($data['gender']) && ! in_array(strtolower($data['gender']), ['male', 'female'])) {
            $errors[] = ['row' => $rowNum, 'field' => 'gender', 'message' => "Row {$rowNum}: Gender must be 'Male' or 'Female'."];
        }

        // Date of birth
        if (! empty($data['date_of_birth']) && ! $this->isValidDate($data['date_of_birth'])) {
            $errors[] = ['row' => $rowNum, 'field' => 'date_of_birth', 'message' => "Row {$rowNum}: Invalid date of birth format. Use YYYY-MM-DD."];
        }

        // Admission date
        if (! empty($data['admission_date']) && ! $this->isValidDate($data['admission_date'])) {
            $errors[] = ['row' => $rowNum, 'field' => 'admission_date', 'message' => "Row {$rowNum}: Invalid admission date format. Use YYYY-MM-DD."];
        }

        // Guardian phone
        if (! empty($data['guardian_phone']) && ! preg_match('/^[\d\s\+\-\(\)]{7,20}$/', $data['guardian_phone'])) {
            $errors[] = ['row' => $rowNum, 'field' => 'guardian_phone', 'message' => "Row {$rowNum}: Invalid guardian phone format."];
        }

        // Status
        $validStatuses = array_keys(Student::STATUSES);
        if (! empty($data['status']) && ! in_array(strtolower($data['status']), $validStatuses)) {
            $errors[] = ['row' => $rowNum, 'field' => 'status', 'message' => "Row {$rowNum}: Status must be one of: " . implode(', ', $validStatuses) . '.'];
        }

        return $errors;
    }

    /**
     * Import a single validated row into the database.
     */
    protected function importRow(array $data): void
    {
        DB::transaction(function () use ($data) {
            // Resolve class
            $classId = $this->classCache[$data['class']] ?? null;
            if (! $classId) {
                throw new \RuntimeException("Class '{$data['class']}' not found.");
            }

            // Resolve section
            $sectionId = null;
            if (! empty($data['section']) && $classId) {
                $key = $classId . ':' . $data['section'];
                $sectionId = $this->sectionCache[$key] ?? null;
            }

            // Resolve or create guardian
            $guardianId = null;
            if (! empty($data['guardian_name']) && ! empty($data['guardian_phone'])) {
                $guardian = Guardian::firstOrCreate(
                    [
                        'school_id' => $this->schoolId,
                        'phone' => $data['guardian_phone'],
                    ],
                    [
                        'name' => $data['guardian_name'],
                        'email' => $data['guardian_email'] ?? null,
                        'relationship' => $data['guardian_relationship'] ?? 'guardian',
                    ]
                );
                $guardianId = $guardian->id;
            }

            // Enrolment: existing students come in confirmed; new admissions provisional.
            $enrolment = $this->confirmOnImport
                ? [
                    'enrolment_status' => 'confirmed',
                    'confirmed_at' => now(),
                    'confirmed_via' => 'manual',
                ]
                : [
                    'enrolment_status' => 'provisional',
                    'confirmed_at' => null,
                    'confirmed_via' => null,
                ];

            Student::create(array_merge([
                'school_id' => $this->schoolId,
                'admission_no' => $data['admission_no'],
                'lin' => ! empty($data['lin']) ? $data['lin'] : null,
                'nin' => ! empty($data['nin']) ? $data['nin'] : null,
                'name' => $data['name'],
                'school_class_id' => $classId,
                'section_id' => $sectionId,
                'guardian_id' => $guardianId,
                'gender' => ! empty($data['gender']) ? strtolower($data['gender']) : null,
                'date_of_birth' => ! empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
                'admission_date' => ! empty($data['admission_date']) ? $data['admission_date'] : now()->toDateString(),
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'medical_notes' => $data['medical_notes'] ?? null,
                'status' => ! empty($data['status']) ? strtolower($data['status']) : 'active',
            ], $enrolment));
        });
    }

    /**
     * Pre-load lookup caches for performance.
     */
    protected function loadCaches(): void
    {
        // Class name → ID
        $this->classCache = SchoolClass::where('school_id', $this->schoolId)
            ->pluck('id', 'name')
            ->toArray();

        // "classId:sectionName" → section ID
        $this->sectionCache = [];
        Section::where('school_id', $this->schoolId)
            ->get(['id', 'school_class_id', 'name'])
            ->each(function ($section) {
                $key = $section->school_class_id . ':' . $section->name;
                $this->sectionCache[$key] = $section->id;
            });

        // Existing admission numbers for duplicate detection
        $this->existingAdmissionNos = Student::where('school_id', $this->schoolId)
            ->pluck('admission_no')
            ->flip()
            ->toArray();
    }

    /**
     * Check if a string is a valid date.
     */
    protected function isValidDate(string $date): bool
    {
        // Accept YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY
        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'm/d/Y'];

        foreach ($formats as $format) {
            $d = \DateTime::createFromFormat($format, $date);
            if ($d && $d->format($format) === $date) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a CSV template file and return its path.
     */
    public static function generateTemplate(): string
    {
        $path = Storage::disk('local')->path('student-import-template.csv');

        $handle = fopen($path, 'w');
        fputcsv($handle, self::VALID_COLUMNS);
        fputcsv($handle, [
            'John Mukasa',   // name
            '001',           // admission_no
            '',              // lin (blank — often not yet issued)
            '',              // nin
            'Senior 1',      // class
            'A',             // section
            'Male',          // gender
            '2010-03-15',    // date_of_birth
            '2026-02-01',    // admission_date
            '0771234567',    // phone
            'john@example.com', // email
            'Kampala',       // address
            '',              // medical_notes
            'David Mukasa',  // guardian_name
            '0701234567',    // guardian_phone
            'david@example.com', // guardian_email
            'father',        // guardian_relationship
            'active',        // status
        ]);
        fclose($handle);

        return $path;
    }
}
