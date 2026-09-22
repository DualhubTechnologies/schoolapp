<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
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

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)->orderByDesc('starts_on');
    }

    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class)->orderByDesc('paid_on');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
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
}
