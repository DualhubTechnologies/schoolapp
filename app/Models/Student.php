<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Concerns\HasFeeAccount;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

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
    protected static function booted(): void
{
    $sync = function (Student $student): void {
        $student->name = trim("{$student->first_name} {$student->last_name}");
    };

    static::saving($sync);
}

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'admission_no',
                'school_class_id',
                'section_id',
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
        'graduated' => 'Graduated',
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
    public function house(): BelongsTo
{
    return $this->belongsTo(House::class);
}

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
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

    // ── Confirmation logic (whichever trigger fires first) ──

    /**
     * Confirm the student as a full member of the school.
     * Idempotent: if already confirmed, does nothing (keeps the original trigger).
     *
     * @param  string  $via  'payment' or 'manual'
     */
    public function confirmEnrolment(string $via = 'manual'): bool
    {
        if ($this->isConfirmed()) {
            return false; // already confirmed — keep whichever trigger came first
        }

        $this->update([
            'enrolment_status' => 'confirmed',
            'confirmed_at' => now(),
            'confirmed_via' => $via,
        ]);

        return true;
    }

    /**
     * Total paid to date, in the school's currency.
     */
    public function totalPaid(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    // ── Fee resolution ──

    /**
     * Resolve the fee amount for this student's class for a given term/year.
     * Falls back to the most recent active structure for the class if term/year not given.
     */
    public function feeStructure(?string $term = null, ?string $academicYear = null): ?FeeStructure
    {
        if (! $this->school_class_id) {
            return null;
        }

        $query = FeeStructure::where('school_id', $this->school_id)
            ->where('school_class_id', $this->school_class_id)
            ->where('is_active', true);

        if ($term) {
            $query->where('term', $term);
        }

        if ($academicYear) {
            $query->where('academic_year', $academicYear);
        }

        return $query->latest('academic_year')->latest('id')->first();
    }
}