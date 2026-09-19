<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeStructure extends Model
{
    use HasFactory;
    use Auditable;

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
        'per_term' => 'Per term (recurring)',
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
        return $this->currency . ' ' . number_format((float) $this->amount, 0);
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
     * Does this fee apply to a student of the given residency?
     * A fee with no residency set applies to everyone.
     */
    public function appliesToResidency(?int $residencyTypeId): bool
    {
        return $this->residency_type_id === null
            || (int) $this->residency_type_id === (int) $residencyTypeId;
    }
}
