<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class AcademicYear extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'school_id',
        'name',
        'start_date',
        'end_date',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    /**
     * Only one academic year per school can be current. Marking a new one
     * current clears the flag on the rest, so the system always has a
     * single unambiguous answer to "what year are we in?".
     */
    protected static function booted(): void
    {
        static::saving(function (AcademicYear $year) {
            if ($year->is_current && $year->isDirty('is_current')) {
                static::where('school_id', $year->school_id)
                    ->whereKeyNot($year->getKey())
                    ->update(['is_current' => false]);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
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

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class)->orderBy('sequence');
    }

    // ── Helpers ──

    /**
     * The school's current academic year, or null if none is marked.
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
     * The academic year immediately before this one for the same school,
     * by start date. Used when carry-forward crosses a year boundary.
     */
    public function previous(): ?self
    {
        if (! $this->start_date) {
            return null;
        }

        return static::where('school_id', $this->school_id)
            ->whereKeyNot($this->getKey())
            ->whereNotNull('start_date')
            ->where('start_date', '<', $this->start_date)
            ->orderByDesc('start_date')
            ->first();
    }
}
