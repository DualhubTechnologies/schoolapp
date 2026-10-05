<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A payment SchoolPay reported for the school, kept once by its SchoolPay
 * receipt number however often it is reported (web hook and nightly
 * check). School fees matched to a learner become a receipt in SchoolHub;
 * the rest wait on the SchoolPay payments page. Written by
 * App\Services\SchoolPay\SchoolPayPayments.
 *
 * @property int $id
 * @property int $school_id
 * @property string $receipt_number
 * @property string $type
 * @property string $status
 * @property string $source
 * @property string $amount
 * @property Carbon|null $paid_at
 * @property string|null $student_payment_code
 * @property string|null $student_registration_number
 * @property string|null $student_name
 * @property string|null $channel
 * @property string|null $channel_transaction_id
 * @property string|null $fee_description
 * @property int|null $student_id
 * @property int|null $student_payment_id
 * @property array<string, mixed>|null $payload
 */
class SchoolPayTransaction extends Model
{
    public const TYPE_SCHOOL_FEES = 'SCHOOL_FEES';

    public const TYPE_OTHER_FEES = 'OTHER_FEES';

    public const STATUSES = [
        'recorded' => 'Recorded',
        'unmatched' => 'Needs a learner',
        'other_fees' => 'Other fees',
    ];

    protected $table = 'schoolpay_transactions';

    protected $fillable = [
        'school_id',
        'receipt_number',
        'type',
        'status',
        'source',
        'amount',
        'paid_at',
        'student_payment_code',
        'student_registration_number',
        'student_name',
        'channel',
        'channel_transaction_id',
        'fee_description',
        'student_id',
        'student_payment_id',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<StudentPayment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(StudentPayment::class, 'student_payment_id');
    }
}
