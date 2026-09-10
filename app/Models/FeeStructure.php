<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeStructure extends Model
{
    protected $fillable = [
        'school_id',
        'school_class_id',
        'name',
        'frequency',
        'applies_to',
        'term',
        'academic_year',
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
}
