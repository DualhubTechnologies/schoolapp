<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Support\PrivateFiles;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $terms_accepted_at
 * @property bool $schoolpay_enabled
 * @property string|null $schoolpay_school_code
 * @property string|null $schoolpay_api_password
 * @property string|null $schoolpay_webhook_token
 * @property CarbonImmutable|null $schoolpay_synced_at
 * @property string|null $schoolpay_sync_error
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
        'schoolpay_enabled',
        'schoolpay_school_code',
        'schoolpay_api_password',
    ];

    /**
     * Never in the audit trail, nor in the page sent to the browser: the
     * SchoolPay API password, and the secret in the school's web hook
     * address.
     *
     * @var list<string>
     */
    protected array $auditIgnore = ['schoolpay_api_password'];

    protected $hidden = ['schoolpay_api_password', 'schoolpay_webhook_token'];

    /**
     * Deleting a school removes everything it owns through the database's
     * cascades. Two kinds of row block that, because they point at other
     * rows of the same school that may not be deleted while in use:
     * finance entries (their category) and students (their class). They go
     * first, in one transaction with the school, so it is all or nothing.
     */
    public function delete(): ?bool
    {
        return DB::transaction(function (): ?bool {
            DB::table('finance_entries')->where('school_id', $this->getKey())->delete();
            DB::table('students')->where('school_id', $this->getKey())->delete();

            return parent::delete();
        });
    }

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'setup_completed_at' => 'datetime',
            'schoolpay_enabled' => 'boolean',
            'schoolpay_api_password' => 'encrypted',
            'schoolpay_synced_at' => 'datetime',
        ];
    }

    /**
     * A school that turns SchoolPay on gets the secret part of its web
     * hook address, once.
     */
    protected static function booted(): void
    {
        static::saving(function (School $school): void {
            if ($school->schoolpay_enabled && blank($school->schoolpay_webhook_token)) {
                $school->schoolpay_webhook_token = Str::random(48);
            }
        });
    }

    /** SchoolPay is switched on and has what SchoolHub needs to talk to it. */
    public function usesSchoolPay(): bool
    {
        return $this->schoolpay_enabled
            && filled($this->schoolpay_school_code)
            && filled($this->schoolpay_api_password);
    }

    /** Where SchoolPay posts this school's payments (set in the SchoolPay portal). */
    public function schoolPayWebhookUrl(): ?string
    {
        return filled($this->schoolpay_webhook_token)
            ? route('schoolpay.webhook', ['token' => $this->schoolpay_webhook_token])
            : null;
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
