<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One of the school's bank accounts, whose statements are imported and
 * reconciled against SchoolHub (App\Services\Banking\BankReconciliation).
 *
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property string $bank
 * @property string|null $account_number
 * @property string $opening_balance
 * @property Carbon|null $opening_date
 * @property bool $is_active
 */
class BankAccount extends Model
{
    use Auditable;

    protected $fillable = [
        'school_id',
        'name',
        'bank',
        'account_number',
        'opening_balance',
        'opening_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'opening_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return HasMany<BankStatementLine, $this>
     */
    public function statementLines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }

    /** "Centenary Bank · 3100012345", for lists and headings. */
    public function label(): string
    {
        return $this->name.' ('.$this->bank.($this->account_number ? ' · '.$this->account_number : '').')';
    }
}
