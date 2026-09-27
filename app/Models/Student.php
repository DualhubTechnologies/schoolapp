<?php

namespace App\Models;

use App\Concerns\HasFeeAccount;
use App\Services\Subscriptions\SubscriptionManager;
use App\Support\PrivateFiles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Student extends Model
{
    use HasFactory;
    use HasFeeAccount;
    use LogsActivity;

    protected $fillable = [
        'school_id',
        'user_id',
        'guardian_id',
        'school_class_id',
        'section_id',
        'combination_id',
        'residency_type_id',
        'transport_route_id',
        'transport_trip',
        'house_id',
        'admission_no',
        'first_name',
        'last_name',
        'lin',
        'schoolpay_code',
        'nin',
        'name',
        'email',
        'phone',
        'date_of_birth',
        'gender',
        'admission_date',
        'photo',
        'address',
        'medical_notes',
        'status',
        'enrolment_status',
        'confirmed_at',
        'confirmed_via',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'admission_date' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * Keep `name` in step with the two name parts, so everything that
     * reads a full name -- tables, PDFs, the activity log -- keeps working
     * without knowing the name is stored in pieces.
     */
    protected static function booted(): void
    {
        // A learner off the van has no trip either; one back on it rides
        // both ways unless told otherwise.
        static::saving(function (Student $student): void {
            $student->transport_trip = $student->transport_route_id ? ($student->transport_trip ?: 'both') : null;
        });

        // The school's plan caps how many active students it may have.
        static::saving(function (Student $student): void {
            $becomesActive = ($student->status ?? 'active') === 'active'
                && (! $student->exists || $student->getOriginal('status') !== 'active' || $student->isDirty('school_id'));

            if ($becomesActive && $student->school_id) {
                SubscriptionManager::ensureRoomForStudents((int) $student->school_id);
            }
        });

        static::saving(function (Student $student): void {
            $full = trim("{$student->first_name} {$student->last_name}");

            if ($full !== '') {
                $student->name = $full;

                return;
            }

            // Only `name` was given (older code paths): split it into the
            // parts rather than wiping it out.
            if (filled($student->name)) {
                [$first, $last] = array_pad(preg_split('/\s+/', trim($student->name), 2), 2, null);
                $student->first_name = $first;
                $student->last_name = $last;
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'admission_no',
                'schoolpay_code',
                'school_class_id',
                'section_id',
                'residency_type_id',
                'transport_route_id',
                'transport_trip',
                'house_id',
                'status',
                'enrolment_status',
                'confirmed_at',
                'confirmed_via',
            ])
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly([]);
    }

    public const STATUSES = [
        'active' => 'Active',
        'graduated' => 'Completed',
        'withdrawn' => 'Withdrawn',
        'transferred' => 'Transferred',
    ];

    public const GENDERS = [
        'male' => 'Male',
        'female' => 'Female',
    ];

    public const ENROLMENT_STATUSES = [
        'provisional' => 'Provisional',
        'confirmed' => 'Confirmed',
    ];

    // ── Relationships ──

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function combination(): BelongsTo
    {
        return $this->belongsTo(Combination::class);
    }

    /**
     * Subjects the student has chosen: O-Level electives, and the A-Level
     * subsidiary when it differs from the combination's.
     */
    public function electives(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'student_subject')->withTimestamps();
    }

    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class);
    }

    public function residencyType(): BelongsTo
    {
        return $this->belongsTo(ResidencyType::class);
    }

    /**
     * The van route this learner uses, or null when a parent brings them.
     *
     * @return BelongsTo<TransportRoute, $this>
     */
    public function transportRoute(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class);
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(StudentPayment::class);
    }

    // ── Accessors / helpers ──

    public function hasLogin(): bool
    {
        return $this->user_id !== null;
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function isConfirmed(): bool
    {
        return $this->enrolment_status === 'confirmed';
    }

    /**
     * Total paid to date, in the school's currency.
     */
    public function totalPaid(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    // ── Confirmation logic (whichever trigger fires first) ──

    /**
     * Confirm the student as a full member of the school.
     * Idempotent: if already confirmed, does nothing.
     *
     * @param  string  $via  'payment' or 'manual'
     */
    public function confirmEnrolment(string $via = 'manual'): bool
    {
        if ($this->isConfirmed()) {
            return false;
        }

        $this->update([
            'enrolment_status' => 'confirmed',
            'confirmed_at' => now(),
            'confirmed_via' => $via,
        ]);

        return true;
    }

    /**
     * The photo as a short-lived signed link, or the generic avatar.
     */
    public function photoUrl(): string
    {
        return PrivateFiles::url($this->photo) ?? asset('images/student-avatar.svg');
    }

    /**
     * The parent page for this learner: a short private link that needs no
     * login, sent to the guardian by SMS (App\Http\Controllers\ParentPageController).
     */
    public function parentPageUrl(): string
    {
        if (! $this->parent_token) {
            do {
                $token = Str::random(10);
            } while (static::where('parent_token', $token)->exists());

            // Quietly: making the link is not a change to the learner's record.
            $this->forceFill(['parent_token' => $token])->saveQuietly();
        }

        return route('parent.page', ['token' => $this->parent_token]);
    }

    /**
     * How the parent pays by SchoolPay, e.g. "SchoolPay code 1004567890",
     * or null when the learner has no code.
     */
    public function schoolPayText(): ?string
    {
        return filled($this->schoolpay_code) ? "SchoolPay code {$this->schoolpay_code}" : null;
    }

    /**
     * Details worth having that quick admission leaves for later, as
     * label => whether it is filled in.
     *
     * @return array<string, bool>
     */
    public function profileChecklist(): array
    {
        return [
            'photo' => filled($this->photo),
            'sex' => filled($this->gender),
            'date of birth' => filled($this->date_of_birth),
            'parent / guardian' => filled($this->guardian_id),
            'LIN' => filled($this->lin),
            'home address' => filled($this->address),
        ];
    }

    public function profilePercent(): int
    {
        $checklist = $this->profileChecklist();

        return (int) round(count(array_filter($checklist)) / count($checklist) * 100);
    }
}
