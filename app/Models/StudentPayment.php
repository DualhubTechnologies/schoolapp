<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPayment extends Model
{
    protected $fillable = [
        'school_id',
        'student_id',
        'amount',
        'currency',
        'paid_on',
        'method',
        'reference',
        'recorded_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }

    public const METHODS = [
        'cash' => 'Cash',
        'bank' => 'Bank',
        'mobile_money' => 'Mobile Money',
        'schoolpay' => 'School Pay',
    ];

    /**
     * When a payment is recorded, confirm the student's enrolment
     * (if not already confirmed by the registrar). This is the
     * "first payment" arm of the whichever-comes-first rule.
     * Later, a School Pay postback can create a StudentPayment and
     * this same hook fires — one confirmation path, two entry points.
     */
    protected static function booted(): void
    {
        static::created(function (StudentPayment $payment) {
            $payment->student?->confirmEnrolment('payment');
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function formattedAmount(): string
    {
        return $this->currency . ' ' . number_format((float) $this->amount, 0);
    }
}
