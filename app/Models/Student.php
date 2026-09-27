<?php

namespace App\Models;

use App\Concerns\HasFeeAccount;
use App\Support\PrivateFiles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Student extends Model
{
    use HasFactory;
    use LogsActivity;
    use HasFeeAccount;

    protected $fillable = [
        'school_id',
        'user_id',
        'guardian_id',
        'school_class_id',
        'section_id',
        'combination_id',
        'residency_type_id',
        'house_id',
        'admission_no',
        'first_name',
        'last_name',
        'lin',
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
        // The school's plan caps how many active students it may have.
        static::saving(function (Student $student): void {
            $becomesActive = ($student->status ?? 'active') === 'active'
                && (! $student->exists || $student->getOriginal('status') !== 'active' || $student->isDirty('school_id'));

            if ($becomesActive && $student->school_id) {
                \App\Services\Subscriptions\SubscriptionManager::ensureRoomForStudents((int) $student->school_id);
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
                'school_class_id',
                'section_id',
                'residency_type_id',
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

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

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
    public function electives(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
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
}
