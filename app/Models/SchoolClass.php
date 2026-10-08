<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    use Auditable;
    use HasFactory;

    protected $fillable = [
        'school_id',
        'class_level_id',
        'name',
        'level',
        'class_teacher_id',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * The academic level this class sits in — O-Level, A-Level, and so on.
     *
     * Note the older `level` column above is a sort position, not an
     * academic level. Two similar names, two different things.
     */
    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class);
    }

    /** Class teacher of the whole class -- used when it has no streams. */
    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'class_teacher_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * Subjects taught in this class, with whether each is compulsory
     * here and who teaches it.
     *
     * @return BelongsToMany<Subject, $this, ClassSubject>
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subject')
            ->using(ClassSubject::class)
            ->withPivot(['id', 'is_compulsory', 'teacher_id'])
            ->withTimestamps()
            ->orderBy('subjects.sort_order')
            ->orderBy('subjects.name');
    }

    /**
     * primary | o_level | a_level | nursery -- from the class's level.
     */
    public function curriculum(): ?string
    {
        return $this->classLevel?->curriculum;
    }

    /**
     * The class's number within its section: S3 -> 3, P.6 -> 6, Senior 5
     * -> 5. Falls back to the sort position when the name has no digit.
     */
    public function number(): ?int
    {
        return preg_match('/(\d+)/', (string) $this->name, $m) ? (int) $m[1] : ($this->level ?: null);
    }
}
