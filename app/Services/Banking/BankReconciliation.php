<?php

namespace App\Services\Banking;

use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\FinanceCategory;
use App\Models\FinanceEntry;
use App\Models\StudentPayment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Bank reconciliation for one account: import the bank's statement, match
 * each line to what SchoolHub recorded, and deal with the rest.
 *
 * - Money in is matched to fee receipts paid by bank or SchoolPay, and to
 *   other income received by bank or cheque; money out to expenses paid by
 *   bank or cheque. Same amount, within a few days.
 * - Matching by itself happens only when it is clear: one candidate, or
 *   one whose reference appears on the statement line.
 * - What only the bank knew (bank charges, interest, a deposit nobody
 *   recorded) can be recorded as an expense or income from the line.
 *   Lines with no SchoolHub record by design (salaries, transfers between
 *   the school's own accounts) are marked explained, with a note.
 */
class BankReconciliation
{
    /** Days either side of the statement date a record may fall on. */
    public const MATCH_DAYS = 3;

    /** Wider window for the bursar's own choice. */
    public const SUGGEST_DAYS = 14;

    public function __construct(protected BankStatementReader $reader) {}

    /**
     * @return array{added: int, already: int}
     */
    public function import(BankAccount $account, string $path, ?string $originalName = null): array
    {
        $lines = $this->reader->read($path, $originalName);
        $counts = ['added' => 0, 'already' => 0];
        $seen = [];

        DB::transaction(function () use ($account, $lines, &$counts, &$seen): void {
            foreach ($lines as $line) {
                // Two identical lines in one file are both real (two equal
                // deposits on one day); the same file uploaded again is not.
                $key = implode('|', [$line['date'], $line['description'], $line['reference'], $line['in'], $line['out'], $line['balance']]);
                $seen[$key] = ($seen[$key] ?? 0) + 1;
                $fingerprint = hash('sha256', $key.'|'.$seen[$key]);

                $line = BankStatementLine::firstOrCreate(
                    ['bank_account_id' => $account->getKey(), 'fingerprint' => $fingerprint],
                    [
                        'school_id' => $account->school_id,
                        'line_date' => $line['date'],
                        'description' => $line['description'],
                        'reference' => $line['reference'],
                        'money_in' => $line['in'],
                        'money_out' => $line['out'],
                        'balance' => $line['balance'],
                        'status' => 'unmatched',
                    ],
                );

                $line->wasRecentlyCreated ? $counts['added']++ : $counts['already']++;
            }
        });

        return $counts;
    }

    /**
     * Match every unmatched line that has one clear SchoolHub record.
     *
     * @return int lines matched
     */
    public function autoMatch(BankAccount $account): int
    {
        $matched = 0;

        $lines = $account->statementLines()->where('status', 'unmatched')->orderBy('line_date')->orderBy('id')->get();

        foreach ($lines as $line) {
            $candidates = $this->candidates($line, self::MATCH_DAYS);

            $byReference = $candidates->filter(fn (Model $record): bool => $this->referenceAppears($record, $line));

            $choice = match (true) {
                $byReference->count() === 1 => $byReference->first(),
                $candidates->count() === 1 => $candidates->first(),
                default => null,
            };

            if ($choice) {
                $this->match($line, $choice, 'Matched automatically');
                $matched++;
            }
        }

        return $matched;
    }

    /**
     * Receipts and vouchers this line could be, closest date first. Records
     * already matched to another line are left out.
     *
     * @return Collection<int, Model>
     */
    public function candidates(BankStatementLine $line, int $days = self::SUGGEST_DAYS): Collection
    {
        $from = CarbonImmutable::parse($line->line_date)->subDays($days)->toDateString();
        $to = CarbonImmutable::parse($line->line_date)->addDays($days)->toDateString();
        $amount = $line->amount();

        $records = collect();

        if ($line->isMoneyIn()) {
            $records = $records->merge($this->unmatched(StudentPayment::query())
                ->where('student_payments.school_id', $line->school_id)
                ->whereIn('method', ['bank', 'schoolpay'])
                ->where('amount', $amount)
                ->whereBetween('paid_on', [$from, $to])
                ->with('student')
                ->get());
        }

        $records = $records->merge($this->unmatched(FinanceEntry::query())
            ->where('finance_entries.school_id', $line->school_id)
            ->where('type', $line->isMoneyIn() ? 'income' : 'expense')
            ->whereIn('method', ['bank', 'cheque'])
            ->where('amount', $amount)
            ->whereBetween('entry_date', [$from, $to])
            ->get());

        $lineDate = CarbonImmutable::parse($line->line_date);

        return $records
            ->sortBy(fn (Model $record): int => (int) abs($lineDate->diffInDays(CarbonImmutable::parse($this->recordDate($record)))))
            ->values();
    }

    /** Tie a statement line to the receipt or voucher that explains it. */
    public function match(BankStatementLine $line, Model $record, ?string $note = null): void
    {
        if (! $record instanceof StudentPayment && ! $record instanceof FinanceEntry) {
            throw new RuntimeException('Only fee receipts and income or expense vouchers can be matched.');
        }

        if ((int) $record->getAttribute('school_id') !== $line->school_id) {
            throw new RuntimeException('That record is not in this school.');
        }

        $taken = BankStatementLine::where('matchable_type', $record->getMorphClass())
            ->where('matchable_id', $record->getKey())
            ->whereKeyNot($line->getKey())
            ->exists();

        if ($taken) {
            throw new RuntimeException('That record is already matched to another statement line.');
        }

        $line->forceFill([
            'status' => 'matched',
            'matchable_type' => $record->getMorphClass(),
            'matchable_id' => $record->getKey(),
            'note' => $note,
            'matched_by' => auth()->user()->name ?? 'SchoolHub',
            'matched_at' => now(),
        ])->save();
    }

    /**
     * Money only the bank knew about (bank charges, interest, an
     * unrecorded deposit): record it as income or an expense paid by bank,
     * matched to this line.
     */
    public function recordEntry(BankStatementLine $line, int $categoryId, string $description, ?string $party = null): FinanceEntry
    {
        $type = $line->isMoneyIn() ? 'income' : 'expense';

        $category = FinanceCategory::where('school_id', $line->school_id)->where('type', $type)->whereKey($categoryId)->first();

        if (! $category) {
            throw new RuntimeException($type === 'income' ? 'Pick an income category.' : 'Pick an expense category.');
        }

        return DB::transaction(function () use ($line, $type, $category, $description, $party): FinanceEntry {
            $entry = FinanceEntry::create([
                'school_id' => $line->school_id,
                'type' => $type,
                'finance_category_id' => $category->getKey(),
                'entry_date' => $line->line_date,
                'amount' => $line->amount(),
                'party' => $party,
                'description' => $description,
                'method' => 'bank',
                'reference' => $line->reference ?: mb_substr($line->description, 0, 100),
            ]);

            $this->match($line, $entry, 'Recorded from the bank statement');

            return $entry;
        });
    }

    /** No SchoolHub record by design: salaries, transfers between the school's accounts... */
    public function explain(BankStatementLine $line, string $note): void
    {
        $line->forceFill([
            'status' => 'explained',
            'matchable_type' => null,
            'matchable_id' => null,
            'note' => mb_substr($note, 0, 255),
            'matched_by' => auth()->user()->name ?? 'SchoolHub',
            'matched_at' => now(),
        ])->save();
    }

    /** Back to "not matched", e.g. after a wrong match. */
    public function unmatch(BankStatementLine $line): void
    {
        $line->forceFill([
            'status' => 'unmatched',
            'matchable_type' => null,
            'matchable_id' => null,
            'note' => null,
            'matched_by' => null,
            'matched_at' => null,
        ])->save();
    }

    /**
     * Balance per SchoolHub's statement lines and what is still open, for
     * the account page.
     *
     * @return array{statement_balance: ?float, unmatched_in: float, unmatched_out: float, unmatched: int}
     */
    public function summary(BankAccount $account): array
    {
        $open = $account->statementLines()->where('status', 'unmatched');

        $last = $account->statementLines()->whereNotNull('balance')->orderByDesc('line_date')->orderByDesc('id')->value('balance');

        return [
            'statement_balance' => $last !== null ? (float) $last : null,
            'unmatched_in' => (float) (clone $open)->sum('money_in'),
            'unmatched_out' => (float) (clone $open)->sum('money_out'),
            'unmatched' => (clone $open)->count(),
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function unmatched(Builder $query): Builder
    {
        $model = $query->getModel();
        $table = $model->getTable();

        return $query->whereNotExists(fn ($sub) => $sub->selectRaw('1')
            ->from('bank_statement_lines')
            ->where('bank_statement_lines.matchable_type', $model->getMorphClass())
            ->whereColumn('bank_statement_lines.matchable_id', "{$table}.id"));
    }

    protected function referenceAppears(Model $record, BankStatementLine $line): bool
    {
        $haystack = strtoupper($line->description.' '.$line->reference);
        $needles = $record instanceof StudentPayment
            ? [$record->reference, $record->receipt_no]
            : [$record->getAttribute('reference'), $record->getAttribute('voucher_no')];

        foreach ($needles as $needle) {
            $needle = strtoupper(trim((string) $needle));

            if (strlen($needle) >= 4 && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function recordDate(Model $record): string
    {
        return (string) ($record instanceof StudentPayment ? $record->paid_on : $record->getAttribute('entry_date'));
    }
}
