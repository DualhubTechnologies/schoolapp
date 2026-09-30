<?php

namespace App\Services;

use App\Models\Staff;
use App\Models\StaffBankDetail;
use App\Models\StaffSalary;
use App\Support\ImportDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Two-phase CSV import of staff, like the student import: validate()
 * previews the file, import() writes it.
 *
 * Both phases run the same per-row checks, so what the preview calls valid
 * is exactly what gets imported. A row with any problem -- missing name,
 * staff number or job title, a bad date or amount, a staff number repeated
 * in the file -- is skipped whole. A staff number the school already has
 * is left alone: that person is already registered.
 *
 * Staff lists are short, so the import runs straight away, not queued.
 */
class StaffCsvImporter
{
    public const REQUIRED_COLUMNS = ['name', 'staff_no', 'position'];

    /** Columns written to the downloadable template, in order. */
    public const TEMPLATE_COLUMNS = [
        'name',
        'staff_no',
        'position',
        'category',
        'department',
        'gender',
        'date_of_birth',
        'phone',
        'email',
        'nin',
        'employment_type',
        'employment_date',
        'initials',
        'tin_number',
        'nssf_number',
        'pays_nssf',
        'pays_lst',
        'basic_salary',
        'bank_name',
        'bank_branch',
        'account_name',
        'account_number',
        'mobile_money_provider',
        'mobile_money_number',
        'status',
    ];

    /** How many issues are sent back to the preview screen. */
    protected const ERROR_DISPLAY_LIMIT = 100;

    /** @var array<string, string> upper-cased staff number => name of whoever has it */
    protected array $existingStaffNos = [];

    public function __construct(protected int $schoolId) {}

    /**
     * Phase 1: check the file without importing anything.
     *
     * @return array{valid: bool, total_rows: int, valid_rows: int, invalid_rows: int, existing_rows: int, error_count: int, errors: list<array<string, mixed>>, existing: list<array<string, mixed>>, preview: list<array<string, string>>, unknown_columns: list<string>}
     */
    public function validate(string $file): array
    {
        $path = Storage::disk('local')->path($file);
        $headers = file_exists($path) ? $this->readHeaders($path) : null;

        if ($headers === null) {
            return $this->failure('The file is empty or has no header row.');
        }

        $missing = array_values(array_diff(self::REQUIRED_COLUMNS, $headers));

        if ($missing) {
            return $this->failure('Missing required columns: '.implode(', ', $missing).'. Download the template to see the layout.');
        }

        $this->loadExisting();
        $fileCounts = $this->countStaffNos($path);

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
                $preview[] = array_intersect_key($data, array_flip(['name', 'staff_no', 'position', 'category', 'department', 'phone', 'basic_salary']));
            }
        }

        return [
            'valid' => $invalidRows === 0,
            'total_rows' => $totalRows,
            'valid_rows' => $totalRows - $invalidRows - count($existing),
            'invalid_rows' => $invalidRows,
            'existing_rows' => count($existing),
            'error_count' => count($errors),
            'errors' => array_slice($errors, 0, self::ERROR_DISPLAY_LIMIT),
            'existing' => array_slice($existing, 0, self::ERROR_DISPLAY_LIMIT),
            'preview' => $preview,
            'unknown_columns' => array_values(array_diff($headers, self::TEMPLATE_COLUMNS)),
        ];
    }

    /**
     * Phase 2: import every row that passes; skip the rest.
     *
     * @return array{imported: int, skipped: int, problems: list<array<string, mixed>>}
     */
    public function import(string $file): array
    {
        $path = Storage::disk('local')->path($file);

        // Checked again against the database as it is now: someone may have
        // added staff since the preview.
        $this->loadExisting();
        $fileCounts = $this->countStaffNos($path);

        $imported = 0;
        $problems = [];

        foreach ($this->rows($path) as $rowNum => $data) {
            $rowErrors = $this->validateRow($data, $rowNum, $fileCounts);

            if ($rowErrors) {
                $problems[] = $rowErrors[0];

                continue;
            }

            try {
                $this->importRow($data);
                $this->existingStaffNos[$this->key($data['staff_no'])] = $data['name'];
                $imported++;
            } catch (\Throwable $e) {
                $problems[] = ['row' => $rowNum, 'field' => 'import', 'type' => 'error', 'message' => 'Could not be saved: '.$e->getMessage()];
                Log::warning("Staff import row {$rowNum} failed", ['school_id' => $this->schoolId, 'error' => $e->getMessage()]);
            }
        }

        Storage::disk('local')->delete($file);

        return ['imported' => $imported, 'skipped' => count($problems), 'problems' => array_slice($problems, 0, self::ERROR_DISPLAY_LIMIT)];
    }

    /**
     * An empty result means the row will import.
     *
     * @param  array<string, string>  $data
     * @param  array<string, int>  $fileCounts
     * @return list<array{row: int, field: string, type: string, message: string}>
     */
    protected function validateRow(array $data, int $rowNum, array $fileCounts): array
    {
        $staffNo = $data['staff_no'];

        if ($staffNo !== '' && isset($this->existingStaffNos[$this->key($staffNo)])) {
            return [[
                'row' => $rowNum,
                'field' => 'staff_no',
                'type' => 'exists',
                'message' => "{$staffNo} is already registered ({$this->existingStaffNos[$this->key($staffNo)]}).",
            ]];
        }

        $errors = [];
        $fail = function (string $field, string $message) use (&$errors, $rowNum): void {
            $errors[] = ['row' => $rowNum, 'field' => $field, 'type' => 'error', 'message' => $message];
        };

        if ($data['name'] === '') {
            $fail('name', 'Name is required.');
        }

        if ($staffNo === '') {
            $fail('staff_no', 'Staff number is required.');
        } elseif (($fileCounts[$this->key($staffNo)] ?? 0) > 1) {
            $fail('staff_no', "Staff number '{$staffNo}' appears {$fileCounts[$this->key($staffNo)]} times in the file.");
        }

        if ($data['position'] === '') {
            $fail('position', 'Position (job title) is required, e.g. Teacher, Bursar, Matron.');
        }

        if ($data['category'] !== '' && $this->category($data['category']) === null) {
            $fail('category', "Category must be 'Teaching' or 'Non-teaching'.");
        }

        if ($data['gender'] !== '' && ! array_key_exists(strtolower($data['gender']), Staff::GENDERS)) {
            $fail('gender', "Gender must be 'Male' or 'Female'.");
        }

        if ($data['employment_type'] !== '' && $this->choice($data['employment_type'], Staff::EMPLOYMENT_TYPES) === null) {
            $fail('employment_type', 'Employment type must be one of: '.implode(', ', Staff::EMPLOYMENT_TYPES).'.');
        }

        if ($data['status'] !== '' && $this->choice($data['status'], Staff::STATUSES) === null) {
            $fail('status', 'Status must be one of: '.implode(', ', Staff::STATUSES).'.');
        }

        foreach (['date_of_birth' => 'date of birth', 'employment_date' => 'employment date'] as $field => $label) {
            if ($data[$field] !== '' && ! $this->parseDate($data[$field])) {
                $fail($field, "Invalid {$label} '{$data[$field]}'. ".ImportDate::HINT);
            }
        }

        foreach (['pays_nssf', 'pays_lst'] as $field) {
            if ($data[$field] !== '' && $this->yesNo($data[$field]) === null) {
                $fail($field, "{$field} must be Yes or No.");
            }
        }

        if ($data['basic_salary'] !== '' && $this->amount($data['basic_salary']) === null) {
            $fail('basic_salary', 'Basic salary must be an amount in shillings, e.g. 850000.');
        }

        if ($data['phone'] !== '' && ! preg_match('/^[\d\s\+\-\(\)]{7,20}$/', $data['phone'])) {
            $fail('phone', 'Invalid phone number.');
        }

        if ($data['email'] !== '' && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $fail('email', 'Invalid email address.');
        }

        if ($data['mobile_money_number'] !== '' && $data['account_number'] === '' && $this->provider($data['mobile_money_provider']) === null) {
            $fail('mobile_money_provider', "Mobile money provider must be 'MTN' or 'Airtel'.");
        }

        return $errors;
    }

    /** @param  array<string, string>  $data */
    protected function importRow(array $data): void
    {
        DB::transaction(function () use ($data): void {
            $employed = $this->parseDate($data['employment_date']) ?? now()->toDateString();

            $staff = Staff::create([
                'school_id' => $this->schoolId,
                'name' => $data['name'],
                'staff_no' => $data['staff_no'],
                'position' => $data['position'],
                'category' => $this->category($data['category']) ?? 'teaching',
                'department' => $data['department'] ?: null,
                'gender' => $data['gender'] !== '' ? strtolower($data['gender']) : null,
                'date_of_birth' => $this->parseDate($data['date_of_birth']),
                'phone' => $data['phone'] ?: null,
                'email' => $data['email'] ?: null,
                'nin' => $data['nin'] ?: null,
                'employment_type' => $this->choice($data['employment_type'], Staff::EMPLOYMENT_TYPES) ?? 'permanent',
                'employment_date' => $employed,
                'initials' => $data['initials'] ?: null,
                'tin_number' => $data['tin_number'] ?: null,
                'nssf_number' => $data['nssf_number'] ?: null,
                'pays_nssf' => $this->yesNo($data['pays_nssf']) ?? true,
                'pays_lst' => $this->yesNo($data['pays_lst']) ?? true,
                'status' => $this->choice($data['status'], Staff::STATUSES) ?? 'active',
            ]);

            if (($salary = $this->amount($data['basic_salary'])) !== null && $salary > 0) {
                StaffSalary::create([
                    'school_id' => $this->schoolId,
                    'staff_id' => $staff->id,
                    'base_salary' => $salary,
                    'effective_from' => $employed,
                    'notes' => 'Imported',
                ]);
            }

            if ($data['account_number'] !== '') {
                StaffBankDetail::create([
                    'staff_id' => $staff->id,
                    'payment_method' => 'bank_transfer',
                    'bank_name' => $data['bank_name'] ?: null,
                    'branch' => $data['bank_branch'] ?: null,
                    'account_name' => $data['account_name'] ?: $data['name'],
                    'account_number' => $data['account_number'],
                    'is_primary' => true,
                ]);
            } elseif ($data['mobile_money_number'] !== '') {
                StaffBankDetail::create([
                    'staff_id' => $staff->id,
                    'payment_method' => 'mobile_money',
                    'mobile_money_provider' => $this->provider($data['mobile_money_provider']),
                    'mobile_money_number' => $data['mobile_money_number'],
                    'is_primary' => true,
                ]);
            }
        });
    }

    /**
     * A failure result in the same shape as a full validation, so the
     * preview can always render it.
     *
     * @return array{valid: bool, total_rows: int, valid_rows: int, invalid_rows: int, existing_rows: int, error_count: int, errors: list<array<string, mixed>>, existing: list<array<string, mixed>>, preview: list<array<string, string>>, unknown_columns: list<string>}
     */
    protected function failure(string $message): array
    {
        return [
            'valid' => false,
            'total_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'existing_rows' => 0,
            'error_count' => 1,
            'errors' => [['row' => 0, 'field' => 'file', 'type' => 'error', 'message' => $message]],
            'existing' => [],
            'preview' => [],
            'unknown_columns' => [],
        ];
    }

    /**
     * The header row: lower case, trimmed, spaces and dashes as
     * underscores, any UTF-8 BOM removed.
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

        return array_map(
            fn ($h): string => str_replace([' ', '-'], '_', strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)))),
            $raw,
        );
    }

    /**
     * Each non-empty row keyed by its line in the spreadsheet (the header
     * is line 1), with every template column present.
     *
     * @return \Generator<int, array<string, string>>
     */
    protected function rows(string $path): \Generator
    {
        $headers = $this->readHeaders($path) ?? [];
        $handle = fopen($path, 'r');

        if (! $handle) {
            return;
        }

        fgetcsv($handle);
        $line = 1;

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $line++;

                if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                $data = array_fill_keys(self::TEMPLATE_COLUMNS, '');

                foreach ($headers as $i => $column) {
                    $data[$column] = trim((string) ($row[$i] ?? ''));
                }

                // 771234567 -> 0771234567 (Excel drops the leading zero).
                foreach (['phone', 'mobile_money_number'] as $field) {
                    if (preg_match('/^7\d{8}$/', $data[$field])) {
                        $data[$field] = '0'.$data[$field];
                    }
                }

                yield $line => $data;
            }
        } finally {
            fclose($handle);
        }
    }

    /** @return array<string, int> */
    protected function countStaffNos(string $path): array
    {
        $counts = [];

        foreach ($this->rows($path) as $data) {
            if ($data['staff_no'] !== '') {
                $key = $this->key($data['staff_no']);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        return $counts;
    }

    protected function loadExisting(): void
    {
        $this->existingStaffNos = Staff::where('school_id', $this->schoolId)
            ->get(['staff_no', 'name'])
            ->mapWithKeys(fn (Staff $s): array => [$this->key((string) $s->staff_no) => (string) $s->name])
            ->all();
    }

    protected function key(string $value): string
    {
        return mb_strtoupper(trim($value));
    }

    /** "Teaching", "non-teaching", "Non teaching staff" -> the stored key. */
    protected function category(string $value): ?string
    {
        $v = str_replace([' ', '-', '_'], '', strtolower($value));

        return match (true) {
            str_starts_with($v, 'nonteaching'), str_starts_with($v, 'support') => 'non_teaching',
            str_starts_with($v, 'teaching') => 'teaching',
            default => null,
        };
    }

    /**
     * A key or label from one of the model's option lists, any case.
     *
     * @param  array<string, string>  $options
     */
    protected function choice(string $value, array $options): ?string
    {
        $v = str_replace([' ', '-'], '_', strtolower(trim($value)));

        foreach ($options as $key => $label) {
            if ($v === $key || $v === str_replace([' ', '-'], '_', strtolower($label))) {
                return $key;
            }
        }

        return null;
    }

    protected function yesNo(string $value): ?bool
    {
        return match (strtolower(trim($value))) {
            'yes', 'y', 'true', '1' => true,
            'no', 'n', 'false', '0' => false,
            default => null,
        };
    }

    protected function provider(string $value): ?string
    {
        return match (true) {
            str_contains(strtolower($value), 'mtn') => 'MTN',
            str_contains(strtolower($value), 'airtel') => 'Airtel',
            default => null,
        };
    }

    /** "850,000", "UGX 850000" -> 850000; anything else -> null. */
    protected function amount(string $value): ?float
    {
        $clean = str_replace([',', ' ', 'UGX', 'ugx', 'Ugx', 'shs', 'Shs'], '', $value);

        return is_numeric($clean) && (float) $clean >= 0 ? (float) $clean : null;
    }

    protected function parseDate(string $date): ?string
    {
        return ImportDate::parse($date);
    }

    /**
     * A template with a few example staff that validates as it stands:
     * teaching and support staff, one paid by bank and one by mobile money.
     */
    public static function generateTemplate(?int $schoolId = null): string
    {
        $year = (int) now()->format('Y');
        $rows = [
            ['Okello Brian', 'ST-001', 'Head Teacher', 'Teaching', 'Administration', 'Male', '12-05-'.($year - 45), '0772100001', 'brian.okello@example.com', '', 'Permanent', '01-02-'.($year - 6), 'O.B.', '', '', 'Yes', 'Yes', '1500000', 'Stanbic Bank', 'Kampala Road', 'Okello Brian', '9030001234567', '', '', 'Active'],
            ['Nakato Sarah', 'ST-002', 'Teacher', 'Teaching', 'Sciences', 'Female', '03-09-'.($year - 32), '0701100002', '', '', 'Permanent', '01-02-'.($year - 2), 'N.S.', '', '', 'Yes', 'Yes', '850000', 'Centenary Bank', 'Mbarara', 'Nakato Sarah', '3100045678', '', '', 'Active'],
            ['Mugisha Ivan', 'ST-003', 'Teacher', 'Teaching', 'Languages', 'Male', '20-01-'.($year - 28), '0782100003', '', '', 'Contract', '01-02-'.$year, '', '', '', 'Yes', 'Yes', '700000', '', '', '', '', 'MTN', '0772100003', 'Active'],
            ['Achieng Faith', 'ST-004', 'Bursar', 'Non-teaching', 'Accounts', 'Female', '14-07-'.($year - 38), '0756100004', '', '', 'Permanent', '02-05-'.($year - 4), '', '', '', 'Yes', 'Yes', '900000', 'dfcu Bank', 'Wandegeya', 'Achieng Faith', '01234567890', '', '', 'Active'],
            ['Kato Samuel', 'ST-005', 'Driver', 'Non-teaching', 'Transport', 'Male', '30-11-'.($year - 41), '0774100005', '', '', 'Permanent', '15-01-'.($year - 3), '', '', '', 'Yes', 'No', '450000', '', '', '', '', 'Airtel', '0754100005', 'Active'],
        ];

        $file = 'staff-import-template'.($schoolId ? "-{$schoolId}" : '').'.csv';
        $path = Storage::disk('local')->path($file);
        $handle = fopen($path, 'w');

        if (! $handle) {
            throw new \RuntimeException("Could not write the staff template to {$path}.");
        }

        fputcsv($handle, self::TEMPLATE_COLUMNS);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $path;
    }
}
