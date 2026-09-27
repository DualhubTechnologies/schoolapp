<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * An expense paid, or income received other than fees. Numbered per
 * school and type (EXP-000001, INC-000001). A mistake is voided with a
 * reason, never deleted; voided entries are hidden from every total by
 * the "not voided" scope (use withVoided() to see them).
 */
class FinanceEntry extends Model
{
    protected $fillable = [
        'school_id',
        'type',
        'finance_category_id',
        'term_id',
        'entry_date',
        'amount',
        'party',
        'description',
        'method',
        'reference',
        'attachment',
        'recorded_by',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'amount' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
    }

    public const METHODS = [
        'cash' => 'Cash',
        'bank' => 'Bank',
        'mobile_money' => 'Mobile Money',
        'cheque' => 'Cheque',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('notVoided', fn (Builder $q) => $q->whereNull('finance_entries.voided_at'));

        static::creating(function (FinanceEntry $entry) {
            $entry->recorded_by ??= auth()->user()?->name;

            // The term the date falls in, for budgets and reports.
            $entry->term_id ??= Term::where('school_id', $entry->school_id)
                ->whereDate('start_date', '<=', $entry->entry_date)
                ->whereDate('end_date', '>=', $entry->entry_date)
                ->value('id');

            DB::transaction(function () use ($entry) {
                $last = static::withVoided()
                    ->where('school_id', $entry->school_id)
                    ->where('type', $entry->type)
                    ->lockForUpdate()
                    ->max('voucher_seq');

                $entry->voucher_seq = ((int) $last) + 1;
                $entry->voucher_no = ($entry->type === 'income' ? 'INC-' : 'EXP-').str_pad((string) $entry->voucher_seq, 6, '0', STR_PAD_LEFT);
            });
        });
    }

    public static function withVoided(): Builder
    {
        return static::withoutGlobalScope('notVoided');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'finance_category_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function void(string $reason): void
    {
        $this->forceFill([
            'voided_at' => now(),
            'voided_by' => auth()->user()?->name,
            'void_reason' => $reason,
        ])->save();
    }
}
