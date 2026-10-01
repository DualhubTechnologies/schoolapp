<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A text waiting for the Windows app to be online (see SmsSender and
 * `sms:send-queued`).
 *
 * @property int $id
 * @property string $to
 * @property string $message
 * @property int $attempts
 * @property string|null $last_error
 * @property string|null $ref
 * @property Carbon|null $sent_at
 * @property Carbon|null $failed_at
 */
class SmsOutbox extends Model
{
    protected $table = 'sms_outbox';

    /** Real failures (wrong number, no credit...) before a text is given up on. */
    public const MAX_ATTEMPTS = 5;

    protected $fillable = ['to', 'message', 'attempts', 'last_error', 'ref', 'sent_at', 'failed_at'];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWaiting(Builder $query): Builder
    {
        return $query->whereNull('sent_at')->whereNull('failed_at');
    }
}
