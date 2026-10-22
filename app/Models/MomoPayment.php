<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One "Pay now" from a school's phone through MTN MoMo: the prompt sent,
 * then MTN's answer. Only a payment MTN reports as successful, for the
 * amount asked, renews the subscription (MomoCheckout).
 *
 * @property int $id
 * @property int $school_id
 * @property int|null $user_id
 * @property int $plan_id
 * @property string $cycle
 * @property float $amount
 * @property string $currency
 * @property string $phone
 * @property string $reference
 * @property string $status
 * @property string|null $reason
 * @property string|null $financial_transaction_id
 * @property int|null $subscription_id
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property-read School $school
 * @property-read Plan $plan
 * @property-read Subscription|null $subscription
 */
class MomoPayment extends Model
{
    public const PENDING = 'pending';

    public const SUCCESSFUL = 'successful';

    public const FAILED = 'failed';

    protected $fillable = [
        'school_id',
        'user_id',
        'plan_id',
        'cycle',
        'amount',
        'currency',
        'phone',
        'reference',
        'status',
        'reason',
        'financial_transaction_id',
        'subscription_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }
}
