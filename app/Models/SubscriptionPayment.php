<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    public const METHODS = [
        'mobile_money' => 'Mobile money',
        'bank' => 'Bank deposit / transfer',
        'cash' => 'Cash',
        'cheque' => 'Cheque',
    ];

    protected $fillable = [
        'school_id',
        'subscription_id',
        'amount',
        'method',
        'reference',
        'paid_on',
        'received_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'paid_on' => 'date',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
