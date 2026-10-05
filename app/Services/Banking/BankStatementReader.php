<?php

namespace App\Services\Banking;

use App\Support\ImportDate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;

/**
 * Reads a bank statement exported from internet banking (CSV or Excel)
 * into plain lines. Built on Centenary Bank's export -- account details
 * above the table, then "Tran Date, Value Date, Tran Particulars, Chq No,
 * Withdrawals, Deposits, Balance" -- and reads the usual other names for
 * the same columns (Narration, Debit/Credit, Reference...), so most banks'
 * statements work too. Opening/closing balance and total rows are skipped.
 */
class BankStatementReader
{
    /** Rows searched for the column headings, below the account details. */
    protected const HEADER_SEARCH_ROWS = 40;

    /**
     * Column headings, as written in lower case with punctuation removed.
     * The first heading found for a field wins.
     *
     * @var array<string, list<string>>
     */
    public const HEADINGS = [
        'date' => ['tran date', 'transaction date', 'txn date', 'trans date', 'posting date', 'post date', 'date', 'value date'],
        'description' => ['tran particulars', 'transaction particulars', 'particulars', 'transaction details', 'narration', 'narrative', 'description', 'details', 'remarks', 'transaction description'],
        'reference' => ['chq no', 'cheque no', 'chq ref no', 'chq ref', 'ref no', 'reference', 'reference no', 'ref', 'instrument no', 'transaction id', 'tran id', 'txn id'],
        'out' => ['withdrawals', 'withdrawal', 'withdrawal amt', 'withdrawal amount', 'debit', 'debits', 'debit amount', 'dr', 'money out', 'paid out'],
        'in' => ['deposits', 'deposit', 'deposit amt', 'deposit amount', 'credit', 'credits', 'credit amount', 'cr', 'money in', 'paid in'],
        'amount' => ['amount', 'transaction amount', 'tran amount'],
        'direction' => ['dr cr', 'cr dr', 'debit credit', 'type', 'tran type', 'part tran type'],
        'balance' => ['balance', 'running balance', 'closing balance', 'available balance', 'book balance'],
    ];

    /**
     * @return list<array{date: string, description: string, reference: ?string, in: float, out: float, balance: ?float}>
     *
     * @throws RuntimeException when the file cannot be read or has no statement table
     */
    public function read(string $path, ?string $originalName = null): array
    {
        $rows = $this->rows($path, $originalName ?? $path);

        [$headerIndex, $columns] = $this->findHeader($rows);

        $lines = [];

        foreach (array_slice($rows, $headerIndex + 1) as $row) {
            $line = $this->line($row, $columns);

            if ($line !== null) {
                $lines[] = $line;
            }
        }

        if ($lines === []) {
            throw new RuntimeException('No transactions were found under the column headings. Is this the right file?');
        }

        return $lines;
    }

    /**
     * @return list<list<string>>
     */
    protected function rows(string $path, string $name): array
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        try {
            if (in_array($extension, ['xlsx', 'xls'], true)) {
                $sheet = IOFactory::load($path)->getActiveSheet();
                $rows = [];

                foreach ($sheet->toArray(null, true, false, false) as $row) {
                    $rows[] = array_values(array_map(fn ($cell): string => $this->cell($cell), $row));
                }

                return $rows;
            }

            return $this->csvRows($path);
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable) {
            throw new RuntimeException('The file could not be read. Save the statement from internet banking as CSV or Excel and try again.');
        }
    }

    /**
     * @return list<list<string>>
     */
    protected function csvRows(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        // Comma, semicolon or tab: whichever the first lines use most.
        $sample = implode("\n", array_slice(preg_split('/\R/', $content) ?: [], 0, 15));
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn (string $d): int => substr_count($sample, $d))->first();

        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('The file could not be read.');
        }

        fwrite($handle, $content);
        rewind($handle);

        $rows = [];

        while (($row = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $rows[] = array_values(array_map(fn ($cell): string => trim((string) $cell), $row));
        }

        fclose($handle);

        return $rows;
    }

    /**
     * The row holding the column headings, and where each field is.
     *
     * @param  list<list<string>>  $rows
     * @return array{0: int, 1: array<string, int>}
     */
    protected function findHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, self::HEADER_SEARCH_ROWS, true) as $index => $row) {
            $columns = [];
            $names = array_map(fn (string $cell): string => trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower($cell))), $row);

            foreach (self::HEADINGS as $field => $aliases) {
                foreach ($aliases as $alias) {
                    $position = array_search($alias, $names, true);

                    if ($position !== false && ! in_array($position, $columns, true)) {
                        $columns[$field] = (int) $position;

                        break;
                    }
                }
            }

            $hasMoney = isset($columns['in']) || isset($columns['out']) || isset($columns['amount']);

            if (isset($columns['date'], $columns['description']) && $hasMoney) {
                return [$index, $columns];
            }
        }

        throw new RuntimeException('The column headings were not found. The statement needs a date, a description and money in/out columns (e.g. Tran Date, Tran Particulars, Withdrawals, Deposits).');
    }

    /**
     * @param  list<string>  $row
     * @param  array<string, int>  $columns
     * @return array{date: string, description: string, reference: ?string, in: float, out: float, balance: ?float}|null
     */
    protected function line(array $row, array $columns): ?array
    {
        $value = fn (string $field): string => isset($columns[$field]) ? trim($row[$columns[$field]] ?? '') : '';

        $date = $this->date($value('date'));
        $description = trim((string) preg_replace('/\s+/', ' ', $value('description')));

        if ($date === null || preg_match('/^(opening|closing) balance|^balance (b\/?f|c\/?f|brought|carried)|^(brought|carried) forward|^totals?\b:?$|^total (debits|credits|withdrawals|deposits)/i', $description)) {
            return null;
        }

        $in = $this->money($value('in'));
        $out = $this->money($value('out'));

        if (isset($columns['amount']) && $in === 0.0 && $out === 0.0) {
            $amount = $this->signedMoney($value('amount'));
            $direction = strtolower($value('direction'));

            if (str_starts_with($direction, 'd') || $amount < 0) {
                $out = abs($amount);
            } else {
                $in = abs($amount);
            }
        }

        if ($in === 0.0 && $out === 0.0) {
            return null;
        }

        $balance = $value('balance');

        return [
            'date' => $date,
            'description' => mb_substr($description !== '' ? $description : '(no description)', 0, 500),
            'reference' => ($reference = $value('reference')) !== '' ? mb_substr($reference, 0, 100) : null,
            'in' => $in,
            'out' => $out,
            'balance' => $balance !== '' ? $this->signedMoney($balance) : null,
        ];
    }

    protected function date(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        // An Excel date cell read as its day number, with or without a time.
        if (is_numeric($value) && (float) $value > 3000 && (float) $value < 80000) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        // "02-10-2026 14:35:10" -> the date part.
        $value = trim((string) preg_replace('/[\sT]+\d{1,2}:\d{2}(:\d{2})?(\s*[AP]M)?$/i', '', $value));

        return ImportDate::parse($value);
    }

    /** A positive amount: "1,250,000.00", "UGX 450,000", "-" or blank (0). */
    protected function money(string $value): float
    {
        return abs($this->signedMoney($value));
    }

    /** "(500.00)", "-500", "500.00 DR" are negative. */
    protected function signedMoney(string $value): float
    {
        $value = strtoupper(trim($value));

        if ($value === '' || $value === '-') {
            return 0.0;
        }

        $negative = str_starts_with($value, '(') || str_starts_with($value, '-') || str_ends_with($value, 'DR');
        $number = (float) preg_replace('/[^0-9.]/', '', $value);

        return $negative ? -$number : $number;
    }

    protected function cell(mixed $cell): string
    {
        return is_scalar($cell) ? trim((string) $cell) : '';
    }
}
