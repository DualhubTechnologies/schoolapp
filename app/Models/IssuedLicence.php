<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Windows app licence the platform owner issued (online server only).
 * The key itself is what the school types in; this record is for finding
 * it again, renewing it and seeing what has been paid.
 *
 * @property int $id
 * @property string $licence_no
 * @property string|null $short_code
 * @property string|null $school_name
 * @property string|null $school_code
 * @property int|null $plan_id
 * @property string $plan_name
 * @property int|null $max_students
 * @property int|null $max_users
 * @property string $cycle
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property string $amount
 * @property string|null $payment_reference
 * @property string|null $notes
 * @property string|null $key
 * @property Carbon|null $activated_at
 * @property int|null $issued_by
 */
class IssuedLicence extends Model
{
    use Auditable;

    public const CYCLES = ['trial' => 'Free trial', 'term' => 'One term', 'year' => 'One year'];

    protected $fillable = [
        'licence_no', 'short_code', 'school_name', 'school_code', 'activated_at', 'plan_id', 'plan_name', 'max_students', 'max_users',
        'cycle', 'starts_on', 'ends_on', 'amount', 'payment_reference', 'notes', 'key', 'issued_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'activated_at' => 'datetime',
            'amount' => 'decimal:2',
            'max_students' => 'integer',
            'max_users' => 'integer',
        ];
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return BelongsTo<User, $this> */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** The next licence number: L-2026-0001, L-2026-0002, ... */
    public static function nextNumber(): string
    {
        $prefix = 'L-'.now()->format('Y').'-';
        $last = static::where('licence_no', 'like', $prefix.'%')->orderByDesc('licence_no')->value('licence_no');

        return $prefix.str_pad((string) ((int) substr((string) $last, strlen($prefix)) + 1), 4, '0', STR_PAD_LEFT);
    }
}
