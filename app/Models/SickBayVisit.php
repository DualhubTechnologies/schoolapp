<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One visit to the sick bay, as in the nurse's register: who came, when,
 * what was wrong, the temperature, what was given and what happened next.
 *
 * @property int $id
 * @property int $school_id
 * @property int|null $student_id
 * @property int|null $staff_id
 * @property int|null $term_id
 * @property Carbon $visited_at
 * @property string $complaint
 * @property string|null $temperature
 * @property string|null $diagnosis
 * @property string|null $treatment
 * @property string $outcome
 * @property string|null $referred_to
 * @property Carbon|null $discharged_at
 * @property Carbon|null $parent_notified_at
 * @property string|null $attended_by
 * @property string|null $notes
 */
class SickBayVisit extends Model
{
    use Auditable;

    public const OUTCOMES = [
        'returned_to_class' => 'Back to class',
        'resting' => 'Resting in the sick bay',
        'sent_home' => 'Sent home',
        'referred' => 'Referred to hospital / clinic',
    ];

    /** Outcomes the parent should hear about straight away. */
    public const SERIOUS = ['sent_home', 'referred'];

    /** Common complaints, offered as suggestions. */
    public const COMPLAINTS = ['Headache', 'Fever', 'Suspected malaria', 'Stomach ache', 'Diarrhoea', 'Vomiting', 'Cough or flu', 'Injury', 'Menstrual pain', 'Toothache', 'Skin rash', 'Eye problem', 'Asthma attack', 'Fainting', 'Wound dressing'];

    protected $fillable = ['school_id', 'student_id', 'staff_id', 'term_id', 'visited_at', 'complaint', 'temperature', 'diagnosis', 'treatment', 'outcome', 'referred_to', 'discharged_at', 'parent_notified_at', 'attended_by', 'notes'];

    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
            'discharged_at' => 'datetime',
            'parent_notified_at' => 'datetime',
            'temperature' => 'decimal:1',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Staff, $this> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /** @return HasMany<SickBayDispense, $this> */
    public function dispensed(): HasMany
    {
        return $this->hasMany(SickBayDispense::class, 'sick_bay_visit_id');
    }

    /**
     * Resting in the sick bay and not yet discharged.
     *
     * @param  Builder<SickBayVisit>  $query
     */
    public function scopeInSickBay(Builder $query): void
    {
        $query->where('outcome', 'resting')->whereNull('discharged_at');
    }

    public function patientName(): string
    {
        if ($this->student) {
            $class = $this->student->schoolClass?->name;

            return $this->student->name.($class ? " ({$class})" : '');
        }

        return ($this->staff->name ?? 'Unknown').' (staff)';
    }

    /** A fever is 37.5 °C or above. */
    public function hasFever(): bool
    {
        return $this->temperature !== null && (float) $this->temperature >= 37.5;
    }
}
