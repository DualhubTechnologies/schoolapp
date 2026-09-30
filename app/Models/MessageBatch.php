<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One bulk SMS: the message, who it went to and how many were delivered
 * to the SMS provider. Sent in the background by App\Jobs\SendMessageBatch.
 *
 * @property int $id
 * @property int $school_id
 * @property string $audience
 * @property array{class_id?: int|null, section_id?: int|null}|null $filters
 * @property string $audience_label
 * @property string $body
 * @property string $status
 * @property int $recipients
 * @property int $sent
 * @property int $failed
 * @property int $no_phone
 * @property int|null $sent_by
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 */
class MessageBatch extends Model
{
    public const AUDIENCES = [
        'families' => 'Parents of all learners',
        'class' => 'Parents of one class or stream',
        'owing' => 'Parents of learners owing fees',
        'staff' => 'All staff',
    ];

    protected $fillable = [
        'school_id',
        'audience',
        'filters',
        'audience_label',
        'body',
        'status',
        'recipients',
        'sent',
        'failed',
        'no_phone',
        'sent_by',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
