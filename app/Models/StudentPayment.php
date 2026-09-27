<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * A fee payment, and the receipt that goes with it.
 *
 * Receipts are numbered per school (RCT-000001, RCT-000002, ...) and are
 * never deleted. A mistake is VOIDED with a reason: the receipt stays in
 * the book, but stops counting towards anything. Voided payments are
 * hidden from every query by the "not voided" global scope; use
 * withVoided() to see them.
 */
class StudentPayment extends Model
{
    use Auditable;

    protected $fillable = [
        'school_id',
        'student_id',
        'term_id',
        'amount',
        'currency',
        'paid_on',
        'method',
        'reference',
        'paid_by',
        'payer_phone',
        'recorded_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    public const METHODS = [
        'cash' => 'Cash',
        'mobile_money' => 'Mobile Money',
        'bank' => 'Bank deposit',
        'schoolpay' => 'SchoolPay',
    ];

    /** Methods where a transaction / slip number must be captured. */
    public const METHODS_NEEDING_REFERENCE = ['mobile_money', 'bank', 'schoolpay'];

    /**
     * SQL fragment for raw queries that sum payments: excludes voided
     * receipts, matching the global scope.
     */
    public const NOT_VOIDED_SQL = 'student_payments.voided_at is null';

    protected static function booted(): void
    {
        static::addGlobalScope('notVoided', fn (Builder $query) => $query->whereNull('student_payments.voided_at'));

        static::creating(function (StudentPayment $payment) {
            $payment->currency ??= 'UGX';
            $payment->recorded_by ??= auth()->user()?->name;

            // Next receipt number for the school. The lock stops two
            // cashiers saving at the same moment getting the same number.
            DB::transaction(function () use ($payment) {
                $last = static::withVoided()
                    ->where('school_id', $payment->school_id)
                    ->lockForUpdate()
                    ->max('receipt_seq');

                $payment->receipt_seq = ((int) $last) + 1;
                $payment->receipt_no = sprintf('RCT-%06d', $payment->receipt_seq);
            });
        });

        /*
         * When a payment is recorded, confirm the student's enrolment
         * (if not already confirmed by the registrar). This is the
         * "first payment" arm of the whichever-comes-first rule.
         */
        static::created(function (StudentPayment $payment) {
            $payment->student?->confirmEnrolment('payment');
        });
    }

    public static function withVoided(): Builder
    {
        return static::withoutGlobalScope('notVoided');
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
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /**
     * Cancel a receipt. It stays in the receipt book, marked void, and no
     * longer counts towards the student's payments.
     */
    public function void(string $reason): void
    {
        $this->forceFill([
            'voided_at' => now(),
            'voided_by' => auth()->user()?->name,
            'void_reason' => $reason,
        ])->save();
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? ucfirst((string) $this->method);
    }

    public function formattedAmount(): string
    {
        return $this->currency.' '.number_format((float) $this->amount, 0);
    }
}
