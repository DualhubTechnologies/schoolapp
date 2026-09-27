<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class FeeStructure extends Model
{
    use Auditable;
    use HasFactory;

    protected $fillable = [
        'school_id',
        'school_class_id',
        'term_id',
        'residency_type_id',
        'name',
        'frequency',
        'applies_to',
        'amount',
        'currency',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public const FREQUENCIES = [
        'per_term' => 'Every term (recurring)',
        'once' => 'Once per student (e.g. admission)',
        'on_demand' => 'On demand (e.g. trip, uniform)',
    ];

    public const APPLIES_TO = [
        'all' => 'All students',
        'new_only' => 'New students only',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function residencyType(): BelongsTo
    {
        return $this->belongsTo(ResidencyType::class);
    }

    public function formattedAmount(): string
    {
        return $this->currency.' '.number_format((float) $this->amount, 0);
    }

    public function isRecurring(): bool
    {
        return $this->frequency === 'per_term';
    }

    public function isOnceOff(): bool
    {
        return $this->frequency === 'once';
    }

    public function isOnDemand(): bool
    {
        return $this->frequency === 'on_demand';
    }

    public function frequencyLabel(): string
    {
        return self::FREQUENCIES[$this->frequency] ?? $this->frequency;
    }

    /**
     * The termly fees in force in a term.
     *
     * A per-term fee's term is the term it STARTS in: it is charged then
     * and in every later term, until a newer version of the same fee --
     * same class, name and residency -- starts in a later term. So:
     *
     *   Tuition S1, from Term 1 2026, 800,000  -> charged every term...
     *   Tuition S1, from Term 1 2027, 850,000  -> ...until this takes over.
     *
     * Switching the newest version off stops the fee from that term on;
     * it does not bring back the older price.
     *
     * @return EloquentCollection<int, FeeStructure>
     */
    public static function termlyFor(Term $term): EloquentCollection
    {
        $target = $term->sortKey();

        $fees = static::query()
            ->where('school_id', $term->school_id)
            ->where('frequency', 'per_term')
            ->with('term.academicYear')
            ->orderBy('id')
            ->get()
            ->filter(fn (FeeStructure $fee) => $fee->startKey() <= $target)
            ->groupBy(fn (FeeStructure $fee) => implode('|', [
                $fee->school_class_id,
                mb_strtolower(trim($fee->name)),
                $fee->residency_type_id ?? 'all',
            ]))
            ->map(fn (Collection $versions) => $versions->sortBy(fn (FeeStructure $fee) => $fee->startKey())->last())
            ->filter(fn (FeeStructure $fee) => $fee->is_active)
            ->values();

        return new EloquentCollection($fees->all());
    }

    /**
     * Sort key of the term this fee starts in. A termly fee with no term
     * (older records) counts as having always applied.
     */
    public function startKey(): string
    {
        return $this->term?->sortKey() ?? '';
    }

    /**
     * Does this fee apply to a student of the given residency?
     * A fee with no residency set applies to everyone.
     */
    public function appliesToResidency(?int $residencyTypeId): bool
    {
        return $this->residency_type_id === null
            || (int) $this->residency_type_id === (int) $residencyTypeId;
    }
}
