<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One paid (or trial) period of a school's subscription. Renewals add a
 * new period; history is never overwritten.
 *
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 */
class Subscription extends Model
{
    public const CYCLES = [
        'trial' => 'Trial',
        'term' => 'One term',
        'year' => 'One year',
        'custom' => 'Custom period',
    ];

    protected $fillable = [
        'school_id',
        'plan_id',
        'cycle',
        'starts_on',
        'ends_on',
        'amount',
        'is_cancelled',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'amount' => 'float',
            'is_cancelled' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function isTrial(): bool
    {
        return $this->cycle === 'trial';
    }

    public function periodLabel(): string
    {
        return $this->starts_on->format('j M Y').' – '.$this->ends_on->format('j M Y');
    }
}
