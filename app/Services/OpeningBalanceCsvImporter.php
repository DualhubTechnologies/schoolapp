<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\Term;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Brings each learner's fee balance over from the school's previous
 * system, so a school can switch without typing every balance in.
 *
 * Two phases like the staff import: validate() previews the file,
 * import() writes it, both with the same per-row checks. A row names a
 * learner by admission number and gives what they owe: below zero when
 * they are in credit, blank when there is nothing to bring over.
 *
 * Each learner has one opening balance, a charge carried into the term
 * the bursar picks. Uploading a file again replaces it, so a corrected
 * file never adds a second one; 0 removes it. When the old system had
 * already charged that term's fees (includes_term_fees), billing leaves
 * the learner's term alone, and 0 is kept as "owes nothing" so the term
 * is not billed again.
 */
class OpeningBalanceCsvImporter
{
    public const REQUIRED_COLUMNS = ['admission_no', 'balance'];

    /** Columns of the opening balances sheet. name and class are there to read; only admission_no and balance are imported. */
    public const TEMPLATE_COLUMNS = ['admission_no', 'name', 'class', 'balance'];

    public const OWED = 'Opening balance (from previous system)';

    public const CREDIT = 'Opening credit (from previous system)';

    public const CLEARED = 'Fees cleared in previous system';

    /** Larger than any fees balance; also keeps clear of the column's limit. */
    protected const MAX_AMOUNT = 1_000_000_000;

    /** How many issues are sent back to the preview screen. */
    protected const ERROR_DISPLAY_LIMIT = 100;

    /** @var array<string, array{id: int, name: string, class: string}> upper-cased admission number => learner */
    protected array $learners = [];

    /** @var array<int, StudentCharge> student id => their opening balance */
    protected array $openingBalances = [];

    public function __construct(protected int $schoolId) {}

    /**
     * Phase 1: check the file without importing anything.
     *
     * @return array{valid: bool, total_rows: int, valid_rows: int, replacing_rows: int, blank_rows: int, invalid_rows: int, error_count: int, errors: list<array<string, mixed>>, total_owed: float, total_credit: float, unbilled_learners: int, preview: list<array<string, string>>, unknown_columns: list<string>}
     */
    public function validate(string $file, Term $term, bool $includesTermFees): array
    {
        $path = Storage::disk('local')->path($file);
        $headers = file_exists($path) ? $this->readHeaders($path) : null;

        if ($headers === null) {
            return $this->failure('The file is empty or has no header row.');
        }

        $missing = array_values(array_diff(self::REQUIRED_COLUMNS, $headers));

        if ($missing) {
            return $this->failure('Missing required columns: '.implode(', ', $missing).'. Download the opening balances sheet to see the layout.');
        }

        $this->load();
        $fileCounts = $this->countAdmissionNos($path);

        $errors = [];
        $preview = [];
        $given = [];
        $totalRows = 0;
        $validRows = 0;
        $replacingRows = 0;
        $blankRows = 0;
        $invalidRows = 0;
        $owed = 0.0;
        $credit = 0.0;

        foreach ($this->rows($path) as $rowNum => $data) {
            $totalRows++;

            if ($data['balance'] === '') {
                $blankRows++;

                continue;
            }

            if ($rowErrors = $this->validateRow($data, $rowNum, $fileCounts)) {
                $invalidRows++;
                array_push($errors, ...$rowErrors);

                continue;
            }

            $learner = $this->learners[$this->key($data['admission_no'])];
            $amount = (float) $this->amount($data['balance']);
            $existing = isset($this->openingBalances[$learner['id']]);
            $given[$learner['id']] = true;

            // 0 removes an earlier figure, or marks "owes nothing" when the
            // term's fees are included; otherwise there is nothing to do.
            if (abs($amount) < 0.005 && ! $existing && ! $includesTermFees) {
                $blankRows++;

                continue;
            }

            $validRows++;
            $replacingRows += $existing ? 1 : 0;
            $owed += max($amount, 0.0);
            $credit += max(-$amount, 0.0);

            if (count($preview) < 5) {
                $preview[] = [
                    'admission_no' => $data['admission_no'],
                    'learner' => $learner['name'],
                    'class' => $learner['class'],
                    'balance' => $this->describe($amount),
                ];
            }
        }

        return [
            'valid' => $invalidRows === 0,
            'total_rows' => $totalRows,
            'valid_rows' => $validRows,
            'replacing_rows' => $replacingRows,
            'blank_rows' => $blankRows,
            'invalid_rows' => $invalidRows,
            'error_count' => count($errors),
            'errors' => array_slice($errors, 0, self::ERROR_DISPLAY_LIMIT),
            'total_owed' => $owed,
            'total_credit' => $credit,
            'unbilled_learners' => $includesTermFees ? $this->learnersStillToBill($term, $given) : 0,
            'preview' => $preview,
            'unknown_columns' => array_values(array_diff($headers, self::TEMPLATE_COLUMNS)),
        ];
    }

    /**
     * Phase 2: write every row that passes; skip the rest.
     *
     * @return array{added: int, replaced: int, removed: int, skipped: int, problems: list<array<string, mixed>>}
     */
    public function import(string $file, Term $term, bool $includesTermFees): array
    {
        throw_unless((int) $term->school_id === $this->schoolId, \InvalidArgumentException::class, 'The term belongs to another school.');

        $path = Storage::disk('local')->path($file);

        // Checked again against the database as it is now: learners or
        // balances may have changed since the preview.
        $this->load();
        $fileCounts = $this->countAdmissionNos($path);

        $counts = ['added' => 0, 'replaced' => 0, 'removed' => 0];
        $problems = [];

        foreach ($this->rows($path) as $rowNum => $data) {
            if ($data['balance'] === '') {
                continue;
            }

            if ($rowErrors = $this->validateRow($data, $rowNum, $fileCounts)) {
                $problems[] = $rowErrors[0];

                continue;
            }

            try {
                $outcome = $this->write(
                    $this->learners[$this->key($data['admission_no'])]['id'],
                    (float) $this->amount($data['balance']),
                    $term,
                    $includesTermFees,
                );

                if ($outcome !== null) {
                    $counts[$outcome]++;
                }
            } catch (\Throwable $e) {
                $problems[] = ['row' => $rowNum, 'field' => 'import', 'type' => 'error', 'message' => 'Could not be saved: '.$e->getMessage()];
                Log::warning("Opening balance import row {$rowNum} failed", ['school_id' => $this->schoolId, 'error' => $e->getMessage()]);
            }
        }

        Storage::disk('local')->delete($file);

        return $counts + ['skipped' => count($problems), 'problems' => array_slice($problems, 0, self::ERROR_DISPLAY_LIMIT)];
    }

    /**
     * Add the learner's opening balance, replace the one already there, or
     * remove it (0, unless the term's fees are included).
     *
     * @return 'added'|'replaced'|'removed'|null null when there was nothing to do
     */
    protected function write(int $studentId, float $amount, Term $term, bool $includesTermFees): ?string
    {
        $existing = $this->openingBalances[$studentId] ?? null;

        if (abs($amount) < 0.005 && ! $includesTermFees) {
            if (! $existing) {
                return null;
            }

            $existing->delete();
            unset($this->openingBalances[$studentId]);

            return 'removed';
        }

        $values = [
            'term_id' => $term->getKey(),
            'description' => match (true) {
                $amount > 0 => self::OWED,
                $amount < 0 => self::CREDIT,
                default => self::CLEARED,
            },
            'amount' => round($amount, 2),
            'discount_amount' => 0,
            'charged_on' => ($term->start_date ?? now())->toDateString(),
            'includes_term_fees' => $includesTermFees,
            'created_by' => auth()->user()?->name,
        ];

        if ($existing) {
            $existing->update($values);

            return 'replaced';
        }

        $this->openingBalances[$studentId] = StudentCharge::create($values + [
            'school_id' => $this->schoolId,
            'student_id' => $studentId,
            'currency' => 'UGX',
            'is_opening_balance' => true,
        ]);

        return 'added';
    }

    /**
     * An empty result means the row will import. Only rows with a balance
     * are checked: a blank row has nothing to bring over.
     *
     * @param  array<string, string>  $data
     * @param  array<string, int>  $fileCounts
     * @return list<array{row: int, field: string, type: string, message: string}>
     */
    protected function validateRow(array $data, int $rowNum, array $fileCounts): array
    {
        $errors = [];
        $fail = function (string $field, string $message) use (&$errors, $rowNum): void {
            $errors[] = ['row' => $rowNum, 'field' => $field, 'type' => 'error', 'message' => $message];
        };

        $admissionNo = $data['admission_no'];

        if ($admissionNo === '') {
            $fail('admission_no', 'Admission number is required.');
        } elseif (! isset($this->learners[$this->key($admissionNo)])) {
            $fail('admission_no', "No learner has admission number '{$admissionNo}'. Add or import the learner first.");
        } elseif (($fileCounts[$this->key($admissionNo)] ?? 0) > 1) {
            $fail('admission_no', "Admission number '{$admissionNo}' has a balance {$fileCounts[$this->key($admissionNo)]} times in the file.");
        }

        $amount = $this->amount($data['balance']);

        if ($amount === null) {
            $fail('balance', "Balance '{$data['balance']}' is not an amount. Write shillings, e.g. 450000, or -50000 for a learner in credit.");
        } elseif (abs($amount) >= self::MAX_AMOUNT) {
            $fail('balance', 'Balance must be under 1,000,000,000 shillings.');
        }

        return $errors;
    }

    /**
     * Active learners in classes who have no figure in the file and no
     * opening balance covering the term already: billing the term will
     * still charge them its fees.
     *
     * @param  array<int, true>  $given  student id => has a figure in the file
     */
    protected function learnersStillToBill(Term $term, array $given): int
    {
        return Student::query()
            ->where('school_id', $this->schoolId)
            ->where('status', 'active')
            ->whereNotNull('school_class_id')
            ->whereDoesntHave('charges', fn ($charges) => $charges
                ->where('term_id', $term->getKey())
                ->where('is_opening_balance', true)
                ->where('includes_term_fees', true))
            ->pluck('id')
            ->reject(fn ($id): bool => isset($given[(int) $id]))
            ->count();
    }

    /**
     * A failure result in the same shape as a full validation, so the
     * preview can always render it.
     *
     * @return array{valid: bool, total_rows: int, valid_rows: int, replacing_rows: int, blank_rows: int, invalid_rows: int, error_count: int, errors: list<array<string, mixed>>, total_owed: float, total_credit: float, unbilled_learners: int, preview: list<array<string, string>>, unknown_columns: list<string>}
     */
    protected function failure(string $message): array
    {
        return [
            'valid' => false,
            'total_rows' => 0,
            'valid_rows' => 0,
            'replacing_rows' => 0,
            'blank_rows' => 0,
            'invalid_rows' => 0,
            'error_count' => 1,
            'errors' => [['row' => 0, 'field' => 'file', 'type' => 'error', 'message' => $message]],
            'total_owed' => 0.0,
            'total_credit' => 0.0,
            'unbilled_learners' => 0,
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

                yield $line => $data;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * How many rows with a balance name each admission number.
     *
     * @return array<string, int>
     */
    protected function countAdmissionNos(string $path): array
    {
        $counts = [];

        foreach ($this->rows($path) as $data) {
            if ($data['admission_no'] !== '' && $data['balance'] !== '') {
                $key = $this->key($data['admission_no']);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /** The school's learners by admission number, and their opening balances. */
    protected function load(): void
    {
        $this->learners = Student::query()
            ->where('school_id', $this->schoolId)
            ->whereNotNull('admission_no')
            ->with(['schoolClass', 'section'])
            ->get()
            ->mapWithKeys(fn (Student $s): array => [$this->key((string) $s->admission_no) => [
                'id' => (int) $s->getKey(),
                'name' => (string) $s->name,
                'class' => self::classLabel($s),
            ]])
            ->all();

        $this->openingBalances = StudentCharge::query()
            ->where('school_id', $this->schoolId)
            ->where('is_opening_balance', true)
            ->get()
            ->mapWithKeys(fn (StudentCharge $charge): array => [(int) $charge->student_id => $charge])
            ->all();
    }

    /** "S.1 East": the learner's class and stream. */
    protected static function classLabel(Student $student): string
    {
        return trim(($student->schoolClass->name ?? '').' '.($student->section->name ?? ''));
    }

    protected function key(string $value): string
    {
        return mb_strtoupper(trim($value));
    }

    /** "450,000", "UGX 450000", "450,000/=" -> 450000; "-50,000", "(50,000)" -> -50000; anything else -> null. */
    protected function amount(string $value): ?float
    {
        // Currency words (UGX, UShs, Shs.), the "/=" of "450,000/=", commas and spaces.
        $clean = (string) preg_replace('/U?SHS?\.?|UGX\.?|\/[=-]|[,\s\x{00A0}]/iu', '', $value);

        if (preg_match('/^\((.+)\)$/', $clean, $match)) {
            $clean = '-'.$match[1];
        }

        return is_numeric($clean) ? (float) $clean : null;
    }

    /** "450,000 owed", "50,000 in credit", "Owes nothing". */
    protected function describe(float $amount): string
    {
        return match (true) {
            $amount > 0 => number_format($amount).' owed',
            $amount < 0 => number_format(abs($amount)).' in credit',
            default => 'Owes nothing',
        };
    }

    /**
     * The opening balances sheet: every learner with admission number,
     * name and class, active learners first, and the opening balance
     * already brought over (blank when there is none) to fill in.
     */
    public static function generateTemplate(int $schoolId): string
    {
        $opening = StudentCharge::query()
            ->where('school_id', $schoolId)
            ->where('is_opening_balance', true)
            ->pluck('amount', 'student_id');

        $learners = Student::query()
            ->where('school_id', $schoolId)
            ->whereNotNull('admission_no')
            ->with(['schoolClass', 'section'])
            ->get()
            ->sortBy(fn (Student $s): string => ($s->status === 'active' ? '0' : '1').'|'.($s->schoolClass->name ?? '~').'|'.($s->section->name ?? '').'|'.$s->name);

        $file = "opening-balances-{$schoolId}.csv";
        $path = Storage::disk('local')->path($file);
        $handle = fopen($path, 'w');

        if (! $handle) {
            throw new \RuntimeException("Could not write the opening balances sheet to {$path}.");
        }

        fputcsv($handle, self::TEMPLATE_COLUMNS);

        foreach ($learners as $learner) {
            $amount = $opening[$learner->getKey()] ?? null;

            fputcsv($handle, [
                $learner->admission_no,
                $learner->name,
                self::classLabel($learner),
                $amount === null ? '' : (string) (float) $amount,
            ]);
        }

        fclose($handle);

        return $path;
    }
}
