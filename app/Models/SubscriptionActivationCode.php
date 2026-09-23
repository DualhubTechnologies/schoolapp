<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-time code the platform owner hands to a school after taking their
 * payment (phone call, WhatsApp, in person). The school types it into its
 * own Subscription page to activate the paid period themselves -- nobody
 * from SchoolHub has to touch the admin panel.
 */
class SubscriptionActivationCode extends Model
{
    protected $fillable = [
        'school_id',
        'plan_id',
        'code',
        'cycle',
        'amount',
        'payment_amount',
        'payment_method',
        'payment_reference',
        'payment_notes',
        'custom_ends_on',
        'notes',
        'expires_at',
        'used_at',
        'used_by',
        'revoked_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'payment_amount' => 'float',
            'custom_ends_on' => 'date',
            'expires_at' => 'date',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isRedeemable(): bool
    {
        return ! $this->used_at && ! $this->revoked_at && $this->expires_at->gte(today());
    }

    public function status(): string
    {
        return match (true) {
            (bool) $this->used_at => 'used',
            (bool) $this->revoked_at => 'revoked',
            $this->expires_at->lt(today()) => 'expired',
            default => 'active',
        };
    }
}
