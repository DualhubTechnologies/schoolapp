<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One line of an imported bank statement, and what explains it in
 * SchoolHub: a fee receipt or an income/expense voucher (matched), or a
 * note such as "Salaries" (explained). Imported once, however often the
 * same statement is uploaded (by its fingerprint).
 *
 * @property int $id
 * @property int $school_id
 * @property int $bank_account_id
 * @property Carbon $line_date
 * @property string $description
 * @property string|null $reference
 * @property string $money_in
 * @property string $money_out
 * @property string|null $balance
 * @property string $fingerprint
 * @property string $status
 * @property string|null $matchable_type
 * @property int|null $matchable_id
 * @property string|null $note
 * @property string|null $matched_by
 * @property Carbon|null $matched_at
 */
class BankStatementLine extends Model
{
    public const STATUSES = [
        'unmatched' => 'Not matched',
        'matched' => 'Matched',
        'explained' => 'Explained',
    ];

    protected $fillable = [
        'school_id',
        'bank_account_id',
        'line_date',
        'description',
        'reference',
        'money_in',
        'money_out',
        'balance',
        'fingerprint',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'line_date' => 'date',
            'money_in' => 'decimal:2',
            'money_out' => 'decimal:2',
            'balance' => 'decimal:2',
            'matched_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * The fee receipt or voucher this line was matched to.
     *
     * @return MorphTo<Model, $this>
     */
    public function matchable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isMoneyIn(): bool
    {
        return (float) $this->money_in > 0;
    }

    public function amount(): float
    {
        return $this->isMoneyIn() ? (float) $this->money_in : (float) $this->money_out;
    }

    /** "Receipt RCT-000123 · Aisha Nakato", "EXP-000045 · UMEME"... */
    public function matchLabel(): ?string
    {
        $record = $this->matchable;

        return match (true) {
            $record instanceof StudentPayment => "Receipt {$record->receipt_no}".($record->student ? " · {$record->student->name}" : ''),
            $record instanceof FinanceEntry => $record->voucher_no.' · '.($record->party ?: $record->description),
            default => $this->status === 'explained' ? $this->note : null,
        };
    }
}
