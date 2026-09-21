<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One fee reminder: an SMS to a guardian, or a printed letter.
 */
class FeeReminder extends Model
{
    protected $fillable = [
        'school_id',
        'student_id',
        'term_id',
        'channel',
        'phone',
        'balance',
        'message',
        'status',
        'error',
        'provider_ref',
        'sent_by',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }

    public const CHANNELS = [
        'sms' => 'SMS',
        'letter' => 'Printed letter',
    ];

    public const STATUSES = [
        'sent' => 'Sent',
        'failed' => 'Failed',
        'printed' => 'Printed',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}
