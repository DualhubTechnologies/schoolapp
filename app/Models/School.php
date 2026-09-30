<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Support\PrivateFiles;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $terms_accepted_at
 */
class School extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
        'slug',
        'motto',
        'description',
        'school_type',
        'unique_code',
        'email',
        'nssf_employer_number',
        'tin_number',
        'phone',
        'website',
        'fee_payment_bank',
        'fee_payment_mobile_money',
        'fee_payment_instructions',
        'parent_sms_language',
        'address',
        'city',
        'country',
        'logo',
        'hm_signature',
        'timezone',
        'currency',
        'status',
        'boarding_type',
        'ownership',
        'expected_students',
        'contact_person',
        'contact_title',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'terms_version',
        'terms_accepted_at',
        'terms_accepted_by',
        'terms_accepted_ip',
        'setup_completed_at',
        'setup_choice',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'terms_accepted_at' => 'datetime', 'setup_completed_at' => 'datetime'];
    }

    public const STATUSES = [
        'pending' => 'Awaiting approval',
        'active' => 'Active',
        'suspended' => 'Suspended',
        'inactive' => 'Inactive',
        'rejected' => 'Rejected',
    ];

    public const BOARDING_TYPES = [
        'day' => 'Day school',
        'boarding' => 'Boarding school',
        'mixed' => 'Day & boarding',
    ];

    public const OWNERSHIP = [
        'private' => 'Private',
        'government' => 'Government',
        'government_aided' => 'Government-aided',
        'community' => 'Community',
        'religious' => 'Religious foundation',
    ];

    /**
     * A school is one of these, chosen once when it signs up.
     *
     * This is identity rather than configuration: report cards, grading
     * and class levels all branch on it.
     */
    public const TYPE_PRIMARY = 'primary';

    public const TYPE_SECONDARY = 'secondary';

    public const TYPES = [
        self::TYPE_PRIMARY => 'Primary School',
        self::TYPE_SECONDARY => 'Secondary School',
    ];

    // ── Relationships ──

    public function classLevels(): HasMany
    {
        return $this->hasMany(ClassLevel::class)->orderBy('sort_order');
    }

    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)->orderByDesc('starts_on');
    }

    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class)->orderByDesc('paid_on');
    }

    public function activationCodes(): HasMany
    {
        return $this->hasMany(SubscriptionActivationCode::class)->orderByDesc('created_at');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasOne<IdCardTemplate, $this>
     */
    public function idCardTemplate(): HasOne
    {
        return $this->hasOne(IdCardTemplate::class);
    }

    /**
     * @return HasOne<ReportCardTemplate, $this>
     */
    public function reportCardTemplate(): HasOne
    {
        return $this->hasOne(ReportCardTemplate::class);
    }

    // ── Type helpers ──

    public function isPrimary(): bool
    {
        return $this->school_type === self::TYPE_PRIMARY;
    }

    public function isSecondary(): bool
    {
        return $this->school_type === self::TYPE_SECONDARY;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->school_type] ?? '—';
    }

    /**
     * The head teacher's signature as a short-lived signed link.
     */
    public function signatureUrl(): ?string
    {
        return PrivateFiles::url($this->hm_signature);
    }
}
