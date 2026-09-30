<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentImport;
use App\Services\Subscriptions\SubscriptionManager;
use App\Support\ImportDate;
use App\Support\SchoolType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Two-phase CSV import of students: validate() previews the file, import()
 * writes it.
 *
 * Both phases run the same per-row checks, so what the preview calls valid
 * is exactly what gets imported. A row with any problem -- missing name,
 * unknown class or section, bad date, an admission number already in the
 * system or repeated in the file -- is skipped whole, never half-imported.
 */
class StudentCsvImporter
{
    /**
     * Columns the header must contain. A name is also required, as either
     * first_name (preferred) or the older single `name` column.
     */
    public const REQUIRED_COLUMNS = [
        'admission_no',
        'class',
    ];

    /**
     * Columns written to the downloadable template, in order.
     */
    public const TEMPLATE_COLUMNS = [
        'first_name',
        'last_name',
        'admission_no',
        'class',
        'section',
        'gender',
        'date_of_birth',
        'admission_date',
        'lin',
        'nin',
        'schoolpay_code',
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

    /**
     * All recognised columns. `name` is still accepted from older files and
     * split into first and last name.
     */
    public const VALID_COLUMNS = [
        ...self::TEMPLATE_COLUMNS,
        'name',
    ];

    /** How many issues are sent back to the preview screen. */
    protected const ERROR_DISPLAY_LIMIT = 100;

    protected StudentImport $import;

    protected int $schoolId;

    /** @var array<string, int> upper-cased class name => id */
    protected array $classCache = [];

    /** @var array<string, int> "classId:UPPER SECTION" => id */
    protected array $sectionCache = [];

    /** @var array<string, string> upper-cased admission number => "Name, Class" of the student who has it */
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
        $this->import->update([
            'status' => 'failed',
            'error_message' => $message,
        ]);

        return [
            'valid' => false,
            'total_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'existing_rows' => 0,
            'error_count' => 1,
            'existing' => [],
            'errors' => [['row' => 0, 'field' => $field, 'message' => $message]],
            'preview' => [],
            'unknown_columns' => [],
        ];
    }

    /**
     * Phase 1: Validate the CSV file without importing anything.
     */
    public function validate(): array
    {
        $this->import->update(['status' => 'validating']);
        $this->import->appendLog('Reading CSV file...');

        $path = Storage::disk('local')->path($this->import->file_path);

        if (! file_exists($path)) {
            return $this->failValidation('File not found.');
        }

        $headers = $this->readHeaders($path);

        if ($headers === null) {
            return $this->failValidation('The file is empty or has no header row.', 'header');
        }

        $this->import->appendLog('Checking required columns...');

        $missing = array_diff(self::REQUIRED_COLUMNS, $headers);

        if (! in_array('first_name', $headers, true) && ! in_array('name', $headers, true)) {
            $missing[] = 'first_name';
        }

        if (! empty($missing)) {
            return $this->failValidation('Missing required columns: '.implode(', ', $missing), 'header');
        }

        // Unrecognised columns are ignored, not fatal.
        $unknown = array_values(array_diff($headers, self::VALID_COLUMNS));

        $this->loadCaches();
        $fileCounts = $this->countAdmissionNos($path);

        $this->import->appendLog('Validating records...');

        $errors = [];
        $existing = [];
        $preview = [];
        $totalRows = 0;
        $invalidRows = 0;

        foreach ($this->rows($path) as $rowNum => $data) {
            $totalRows++;

            $rowErrors = $this->validateRow($data, $rowNum, $fileCounts);

            if ($rowErrors && $rowErrors[0]['type'] === 'exists') {
                $existing[] = $rowErrors[0];
            } elseif ($rowErrors) {
                $invalidRows++;
                array_push($errors, ...$rowErrors);
            }

            if (count($preview) < 5) {
                $preview[] = $data;
            }
        }

        $existingRows = count($existing);
        $validRows = $totalRows - $invalidRows - $existingRows;

        $this->import->update([
            'status' => 'validated',
            'total_rows' => $totalRows,
            'failed_rows' => $invalidRows + $existingRows,
            'duplicate_rows' => $existingRows,
            'validation_errors' => [...$errors, ...$existing],
        ]);

        $this->import->appendLog("Validation complete. {$totalRows} rows scanned: {$validRows} new, {$existingRows} already registered, {$invalidRows} with errors.");

        return [
            'valid' => $invalidRows === 0,
            'total_rows' => $totalRows,
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'existing_rows' => $existingRows,
            'error_count' => count($errors),
            'errors' => array_slice($errors, 0, self::ERROR_DISPLAY_LIMIT),
            'existing' => array_slice($existing, 0, self::ERROR_DISPLAY_LIMIT),
            'preview' => $preview,
            'unknown_columns' => $unknown,
            // How many more active students the school's plan allows (null = no limit).
            'plan_room' => SubscriptionManager::roomForStudents($this->schoolId),
        ];
    }

    /**
     * Phase 2: Import every row that passes validation; skip the rest.
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

        // Re-check against the database as it is now, not as it was at
        // preview time -- someone may have added students in between.
        $this->loadCaches();
        $fileCounts = $this->countAdmissionNos($path);

        $processed = 0;
        $successCount = 0;
        $skipCount = 0;
        $skipped = [];

        foreach ($this->rows($path) as $rowNum => $data) {
            $processed++;

            $rowErrors = $this->validateRow($data, $rowNum, $fileCounts);

            if ($rowErrors) {
                $skipCount++;
                // One line per skipped row is enough to explain it.
                $skipped[] = $rowErrors[0];
            } else {
                try {
                    $this->importRow($data);
                    $this->existingAdmissionNos[$this->key($data['admission_no'])] = trim("{$data['first_name']} {$data['last_name']}").", {$data['class']}";
                    $successCount++;
                } catch (\Throwable $e) {
                    $skipCount++;
                    $skipped[] = [
                        'row' => $rowNum,
                        'field' => 'import',
                        'type' => 'error',
                        'message' => $e->getMessage(),
                    ];
                    Log::warning("Student import row {$rowNum} failed", [
                        'import_id' => $this->import->id,
                        'data' => $data,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Update progress every 10 rows to avoid hammering the DB
            if ($processed % 10 === 0) {
                $this->import->update([
                    'processed_rows' => $processed,
                    'successful_rows' => $successCount,
                    'failed_rows' => $skipCount,
                ]);
            }
        }

        $this->import->update([
            'status' => 'completed',
            'total_rows' => $processed,
            'processed_rows' => $processed,
            'successful_rows' => $successCount,
            'failed_rows' => $skipCount,
            'validation_errors' => $skipped ?: null,
            'completed_at' => now(),
        ]);

        $duration = $this->import->started_at->diffInSeconds($this->import->completed_at);
        $this->import->appendLog("Import completed. {$successCount} students imported, {$skipCount} skipped. Duration: {$duration}s.");
    }

    /**
     * Validate a single CSV row. An empty result means the row will import.
     *
     * @param  array<string, int>  $fileCounts  admission number => occurrences in the file
     */
    protected function validateRow(array $data, int $rowNum, array $fileCounts): array
    {
        $admNo = $data['admission_no'] ?? '';

        // Already registered: nothing else about the row matters, it is
        // simply skipped. Reported apart from errors -- it needs no fixing.
        if ($admNo !== '' && isset($this->existingAdmissionNos[$this->key($admNo)])) {
            return [[
                'row' => $rowNum,
                'field' => 'admission_no',
                'type' => 'exists',
                'message' => "{$admNo} is already registered ({$this->existingAdmissionNos[$this->key($admNo)]}).",
            ]];
        }

        $errors = [];
        $fail = function (string $field, string $message) use (&$errors, $rowNum) {
            $errors[] = ['row' => $rowNum, 'field' => $field, 'type' => 'error', 'message' => $message];
        };

        if ($data['first_name'] === '') {
            $fail('first_name', 'First name is required.');
        }

        if ($admNo === '') {
            $fail('admission_no', 'Admission number is required.');
        } elseif (($fileCounts[$this->key($admNo)] ?? 0) > 1) {
            $fail('admission_no', "Admission number '{$admNo}' appears {$fileCounts[$this->key($admNo)]} times in the file.");
        }

        $class = $data['class'] ?? '';
        $classId = $this->classCache[$this->key($class)] ?? null;

        if ($class === '') {
            $fail('class', 'Class is required.');
        } elseif (! $classId) {
            $fail('class', "Class '{$class}' not found. Create it first.");
        }

        $section = $data['section'] ?? '';

        if ($section !== '' && $classId && ! isset($this->sectionCache[$classId.':'.$this->key($section)])) {
            $fail('section', "Section '{$section}' not found under class '{$class}'.");
        }

        if (($data['gender'] ?? '') !== '' && ! in_array(strtolower($data['gender']), ['male', 'female'], true)) {
            $fail('gender', "Gender must be 'Male' or 'Female'.");
        }

        if (($data['date_of_birth'] ?? '') !== '' && ! $this->parseDate($data['date_of_birth'])) {
            $fail('date_of_birth', "Invalid date of birth '{$data['date_of_birth']}'. ".ImportDate::HINT);
        }

        if (($data['admission_date'] ?? '') !== '' && ! $this->parseDate($data['admission_date'])) {
            $fail('admission_date', "Invalid admission date '{$data['admission_date']}'. ".ImportDate::HINT);
        }

        if (($data['guardian_phone'] ?? '') !== '' && ! preg_match('/^[\d\s\+\-\(\)]{7,20}$/', $data['guardian_phone'])) {
            $fail('guardian_phone', 'Invalid guardian phone format.');
        }

        $validStatuses = array_keys(Student::STATUSES);

        if (($data['status'] ?? '') !== '' && ! in_array(strtolower($data['status']), $validStatuses, true)) {
            $fail('status', 'Status must be one of: '.implode(', ', $validStatuses).'.');
        }

        return $errors;
    }

    /**
     * Import a single validated row into the database.
     */
    protected function importRow(array $data): void
    {
        DB::transaction(function () use ($data) {
            $classId = $this->classCache[$this->key($data['class'])];

            $sectionId = ($data['section'] ?? '') !== ''
                ? $this->sectionCache[$classId.':'.$this->key($data['section'])]
                : null;

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
                        'email' => $data['guardian_email'] ?: null,
                        'relationship' => $data['guardian_relationship'] ?: 'guardian',
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

            // `name` is derived from first_name + last_name by the model.
            Student::create(array_merge([
                'school_id' => $this->schoolId,
                'admission_no' => $data['admission_no'],
                'lin' => ($data['lin'] ?? '') ?: null,
                'nin' => ($data['nin'] ?? '') ?: null,
                'schoolpay_code' => ($data['schoolpay_code'] ?? '') ?: null,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?: null,
                'school_class_id' => $classId,
                'section_id' => $sectionId,
                'guardian_id' => $guardianId,
                'gender' => ($data['gender'] ?? '') !== '' ? strtolower($data['gender']) : null,
                'date_of_birth' => $this->parseDate($data['date_of_birth'] ?? ''),
                'admission_date' => $this->parseDate($data['admission_date'] ?? '') ?? now()->toDateString(),
                'phone' => ($data['phone'] ?? '') ?: null,
                'email' => ($data['email'] ?? '') ?: null,
                'address' => ($data['address'] ?? '') ?: null,
                'medical_notes' => ($data['medical_notes'] ?? '') ?: null,
                'status' => ($data['status'] ?? '') !== '' ? strtolower($data['status']) : 'active',
            ], $enrolment));
        });
    }

    /**
     * Read and normalise the header row: lowercase, trimmed, spaces and
     * dashes to underscores, UTF-8 BOM stripped.
     *
     * @return list<string>|null
     */
    protected function readHeaders(string $path): ?array
    {
        $handle = fopen($path, 'r');
        $raw = $handle ? fgetcsv($handle) : false;

        if ($handle) {
            fclose($handle);
        }

        if (! $raw || count(array_filter($raw, fn ($h) => trim((string) $h) !== '')) === 0) {
            return null;
        }

        return array_map(function ($h) {
            $h = preg_replace('/^\xEF\xBB\xBF/', '', (string) $h);

            return str_replace([' ', '-'], '_', strtolower(trim($h)));
        }, $raw);
    }

    /**
     * Yield each non-empty data row, keyed by its line in the spreadsheet
     * (the header is line 1, so the first student is line 2).
     *
     * @return \Generator<int, array<string, string>>
     */
    protected function rows(string $path): \Generator
    {
        $headers = $this->readHeaders($path) ?? [];
        $handle = fopen($path, 'r');
        fgetcsv($handle); // header

        $line = 1;

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $line++;

                if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                $data = [];
                foreach ($headers as $i => $col) {
                    $data[$col] = trim((string) ($row[$i] ?? ''));
                }

                yield $line => $this->normalise($data);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Fill in first/last name from an old-style `name` column, and put back
     * the leading zero spreadsheets strip from phone numbers.
     */
    protected function normalise(array $data): array
    {
        // Columns the file leaves out read as blank.
        $data += array_fill_keys(self::VALID_COLUMNS, '');

        $first = $data['first_name'] ?? '';
        $last = $data['last_name'] ?? '';

        if ($first === '' && $last === '' && ($data['name'] ?? '') !== '') {
            [$first, $last] = array_pad(preg_split('/\s+/', $data['name'], 2), 2, '');
        }

        $data['first_name'] = $first;
        $data['last_name'] = $last;

        foreach (['phone', 'guardian_phone'] as $field) {
            // 771234567 -> 0771234567 (Excel drops the zero on numbers).
            if (preg_match('/^7\d{8}$/', $data[$field] ?? '')) {
                $data[$field] = '0'.$data[$field];
            }
        }

        return $data;
    }

    /**
     * @return array<string, int> upper-cased admission number => occurrences
     */
    protected function countAdmissionNos(string $path): array
    {
        $counts = [];

        foreach ($this->rows($path) as $data) {
            if (($data['admission_no'] ?? '') !== '') {
                $key = $this->key($data['admission_no']);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * Lookup key: matching is case- and space-insensitive, so "s1" finds
     * class "S1" and "a" finds section "A".
     */
    protected function key(string $value): string
    {
        return mb_strtoupper(trim($value));
    }

    /**
     * Pre-load lookup caches for performance.
     */
    protected function loadCaches(): void
    {
        $this->classCache = [];
        SchoolClass::where('school_id', $this->schoolId)
            ->get(['id', 'name'])
            ->each(function ($class) {
                $this->classCache[$this->key($class->name)] = $class->id;
            });

        $this->sectionCache = [];
        Section::where('school_id', $this->schoolId)
            ->get(['id', 'school_class_id', 'name'])
            ->each(function ($section) {
                $this->sectionCache[$section->school_class_id.':'.$this->key($section->name)] = $section->id;
            });

        $this->existingAdmissionNos = [];
        Student::where('school_id', $this->schoolId)
            ->with('schoolClass:id,name')
            ->get(['id', 'admission_no', 'name', 'school_class_id'])
            ->each(function (Student $student) {
                $this->existingAdmissionNos[$this->key((string) $student->admission_no)] = collect([
                    $student->name ?: 'no name on record',
                    $student->schoolClass?->name,
                ])->filter()->implode(', ');
            });
    }

    /** A date as Y-m-d, or null if it is blank or cannot be read. */
    protected function parseDate(string $date): ?string
    {
        return ImportDate::parse($date);
    }

    /**
     * Example learners for the template, one per class in turn.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    protected const TEMPLATE_EXAMPLES = [
        ['Joan', 'Nakato', 'Female', 'Sarah Nakato', 'mother'],
        ['Brian', 'Okello', 'Male', 'John Okello', 'father'],
        ['Grace', 'Namuli', 'Female', 'Robert Namuli', 'father'],
        ['Ivan', 'Mugisha', 'Male', 'Annet Mugisha', 'mother'],
        ['Faith', 'Achieng', 'Female', 'Florence Achieng', 'guardian'],
        ['Samuel', 'Kato', 'Male', 'David Kato', 'father'],
        ['Mercy', 'Nabirye', 'Female', 'Harriet Nabirye', 'mother'],
    ];

    /**
     * Generate a CSV template file and return its path. It shows one
     * example learner in each of the school's own classes (P.1-P.7 in a
     * primary school, S.1-S.6 in a secondary school), with an age to
     * match, so it validates as-is and never asks what kind of school
     * this is. A school with no classes yet gets the usual class names
     * for its type.
     */
    public static function generateTemplate(?int $schoolId = null): string
    {
        $school = $schoolId ? School::find($schoolId) : null;

        $classes = $school
            ? SchoolClass::where('school_id', $school->id)->orderBy('level')->orderBy('name')->with('sections')->get()
                ->map(fn (SchoolClass $class): array => [$class->name, (string) ($class->sections->sortBy('name')->first()->name ?? '')])
                ->all()
            : [];

        if ($classes === []) {
            $classes = collect(SchoolType::keys($school))
                ->reject(fn (string $curriculum): bool => $curriculum === 'nursery')
                ->flatMap(fn (string $curriculum): array => config("academics.classes.{$curriculum}", []))
                ->map(fn (string $name): array => [$name, ''])
                ->all();
        }

        // Age on entering the first class: 6 for P.1, 13 for S.1.
        $firstAge = $school?->school_type === School::TYPE_SECONDARY ? 13 : 6;

        $file = 'student-import-template'.($schoolId ? "-{$schoolId}" : '').'.csv';
        $path = Storage::disk('local')->path($file);

        $handle = fopen($path, 'w');
        fputcsv($handle, self::TEMPLATE_COLUMNS);

        foreach (array_values($classes) as $i => [$className, $section]) {
            [$first, $last, $gender, $guardian, $relationship] = self::TEMPLATE_EXAMPLES[$i % count(self::TEMPLATE_EXAMPLES)];

            fputcsv($handle, [
                $first,                                                  // first_name
                $last,                                                   // last_name
                sprintf('ADM-%04d', $i + 1),                             // admission_no (must be new)
                $className,                                              // class (must already exist)
                $section,                                                // section (optional)
                $gender,                                                 // gender: Male / Female
                '15-03-'.today()->subYears($firstAge + $i)->format('Y'), // date_of_birth: DD-MM-YYYY
                '02-02-'.today()->format('Y'),                           // admission_date: DD-MM-YYYY (blank = today)
                '',                                                      // lin
                '',                                                      // nin
                '',                                                      // schoolpay_code (only if the school uses SchoolPay)
                '',                                                      // phone
                '',                                                      // email
                'Wakiso',                                                // address
                '',                                                      // medical_notes
                $guardian,                                               // guardian_name
                '07'.(71234560 + $i),                                    // guardian_phone
                '',                                                      // guardian_email
                $relationship,                                           // guardian_relationship
                'active',                                                // status: active / graduated / withdrawn / transferred
            ]);
        }

        fclose($handle);

        return $path;
    }
}
