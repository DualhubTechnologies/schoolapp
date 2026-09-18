<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Term extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'name',
        'sequence',
        'start_date',
        'end_date',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    /**
     * Only one term per school can be current. Marking a new one current
     * clears the flag on the rest — invoicing and statements default to
     * whichever term this points at.
     */
    protected static function booted(): void
    {
        static::saving(function (Term $term) {
            if ($term->is_current && $term->isDirty('is_current')) {
                static::where('school_id', $term->school_id)
                    ->whereKeyNot($term->getKey())
                    ->update(['is_current' => false]);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'sequence',
                'academic_year_id',
                'start_date',
                'end_date',
                'is_current',
            ])
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly([]);
    }

    // ── Relationships ──

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    // ── Helpers ──

    /**
     * The school's current term, or null if none is marked.
     */
    public static function current(?int $schoolId = null): ?self
    {
        $schoolId ??= auth()->user()?->school_id;

        if (! $schoolId) {
            return null;
        }

        return static::where('school_id', $schoolId)
            ->where('is_current', true)
            ->first();
    }

    /**
     * The term immediately before this one, stepping back into the
     * previous academic year when this is the first term of its year.
     *
     * Phase 3 (arrears) walks this chain backwards to work out what a
     * student still owes from earlier terms.
     */
    public function previous(): ?self
    {
        $withinYear = static::where('school_id', $this->school_id)
            ->where('academic_year_id', $this->academic_year_id)
            ->where('sequence', '<', $this->sequence)
            ->orderByDesc('sequence')
            ->first();

        if ($withinYear) {
            return $withinYear;
        }

        $previousYear = $this->academicYear?->previous();

        if (! $previousYear) {
            return null;
        }

        return static::where('academic_year_id', $previousYear->getKey())
            ->orderByDesc('sequence')
            ->first();
    }

    /**
     * The term immediately after this one, stepping into the next
     * academic year when this is the last term of its year.
     */
    public function next(): ?self
    {
        $withinYear = static::where('school_id', $this->school_id)
            ->where('academic_year_id', $this->academic_year_id)
            ->where('sequence', '>', $this->sequence)
            ->orderBy('sequence')
            ->first();

        if ($withinYear) {
            return $withinYear;
        }

        $nextYear = AcademicYear::where('school_id', $this->school_id)
            ->whereKeyNot($this->academic_year_id)
            ->whereNotNull('start_date')
            ->where('start_date', '>', $this->academicYear?->start_date)
            ->orderBy('start_date')
            ->first();

        if (! $nextYear) {
            return null;
        }

        return static::where('academic_year_id', $nextYear->getKey())
            ->orderBy('sequence')
            ->first();
    }

    /**
     * "Term 1 — 2026", for dropdowns and statement headings.
     */
    public function label(): string
    {
        $year = $this->academicYear?->name;

        return $year ? "{$this->name} — {$year}" : $this->name;
    }
}
