<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
     * The academic level this class sits in - O-Level, A-Level, and so on.
     *
     * Note the older `level` column above is a sort position, not an
     * academic level. Two similar names, two different things.
     */
    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class);
    }

    /**
     * Some of a school's classes the way a school writes them: "S.1 - S.3"
     * for three or more that follow each other, "S.5 and S.6" for two,
     * "S.1, S.3 and S.5" otherwise.
     *
     * @param  Collection<int, SchoolClass>  $ordered  all the school's classes, in order
     * @param  array<int, int|string|null>  $ids
     */
    public static function joinNames(Collection $ordered, array $ids): string
    {
        $picked = $ordered->values()->filter(fn (SchoolClass $class): bool => in_array($class->getKey(), $ids));
        $positions = $picked->keys()->values();
        $names = $picked->map(fn (SchoolClass $class): string => $class->name)->values();

        $consecutive = $positions->count() > 2 && $positions->last() - $positions->first() === $positions->count() - 1;

        return match (true) {
            $names->isEmpty() => 'no classes',
            $consecutive => $names->first().' - '.$names->last(),
            $names->count() === 1 => $names->first(),
            default => $names->slice(0, -1)->implode(', ').' and '.$names->last(),
        };
    }

    /**
     * The school's classes in order (the sort position, then the name).
     *
     * @return EloquentCollection<int, SchoolClass>
     */
    public static function inOrder(int|string|null $schoolId): EloquentCollection
    {
        return SchoolClass::query()->where('school_id', $schoolId)->orderBy('level')->orderBy('name')->get();
    }

    /**
     * Class teacher of the whole class -- used when it has no streams.
     *
     * @return BelongsTo<Staff, $this>
     */
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
