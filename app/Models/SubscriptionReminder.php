<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One reminder sent to a school about its trial or subscription ending.
 * See App\Services\Subscriptions\SubscriptionReminders.
 */
class SubscriptionReminder extends Model
{
    protected $fillable = ['school_id', 'ends_on', 'kind', 'emails', 'sms', 'note'];

    protected function casts(): array
    {
        return [
            'ends_on' => 'date',
            'sms' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
