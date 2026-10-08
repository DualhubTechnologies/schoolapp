<?php

namespace App\Models;

use App\Support\SchoolType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One exam or piece of continuous assessment in a term, e.g. "Mid-Term
 * Examination" or "Activity of Integration 1". Its weight is its share of
 * the term result.
 *
 * An exam can be set in some classes and subjects only (a CA in S.2
 * Biology, say); none chosen means every class and subject.
 *
 * @property array<int, int|string>|null $class_ids
 * @property array<int, int|string>|null $subject_ids
 */
class Assessment extends Model
{
    /**
     * Project work is reported on its own (marked out of 10, printed in the
     * report card's Project work section), never weighted in the term result.
     * O-Level exams always carry the standard weight for their type.
     */
    protected static function booted(): void
    {
        static::saving(function (Assessment $assessment): void {
            if ($assessment->type === 'project') {
                $assessment->weight = 0;
            }

            // O-Level weights are the standard's (CA 20%, End of Term 80%), not typed.
            $standard = (array) config('academics.default_weights.o_level');
            if ($assessment->curriculum === 'o_level' && array_key_exists((string) $assessment->type, $standard)) {
                $assessment->weight = (float) $standard[$assessment->type];
            }
        });

    }

    /**
     * What a new O-Level Activity of Integration is marked out of; a school
     * may set 100 instead. Report cards show each one out of 3.
     */
    public const O_LEVEL_CA_DEFAULT_MAX = 20;

    /** The scale report cards show O-Level Activities of Integration on (NCDC's 0-3). */
    public const O_LEVEL_CA_REPORT_SCALE = 3;

    protected $fillable = [
        'school_id',
        'term_id',
        'name',
        'type',
        'curriculum',
        'class_ids',
        'subject_ids',
        'max_score',
        'weight',
        'held_on',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'max_score' => 'decimal:2',
            'weight' => 'decimal:2',
            'held_on' => 'date',
            'class_ids' => 'array',
            'subject_ids' => 'array',
        ];
    }

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class);
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public function appliesTo(?string $curriculum): bool
    {
        return $this->curriculum === null || $this->curriculum === $curriculum;
    }

    /** Whether the exam is set in this class: every class unless some are chosen. */
    public function coversClass(?int $classId): bool
    {
        return $this->class_ids === null || $this->class_ids === [] || in_array($classId, array_map('intval', $this->class_ids), true);
    }

    /** Whether the exam is set in this subject: every subject unless some are chosen. */
    public function coversSubject(?int $subjectId): bool
    {
        return $this->subject_ids === null || $this->subject_ids === [] || in_array($subjectId, array_map('intval', $this->subject_ids), true);
    }

    /** Whether the exam is set in this class (and subject, when given). */
    public function covers(SchoolClass $class, ?int $subjectId = null): bool
    {
        return $this->appliesTo($class->curriculum())
            && $this->coversClass((int) $class->getKey())
            && ($subjectId === null || $this->coversSubject($subjectId));
    }

    /** Who sits the exam, for lists: "All classes and subjects", or the chosen ones. */
    public function coverageLabel(): string
    {
        $classes = $this->class_ids ? SchoolClass::whereIn('id', $this->class_ids)->orderBy('level')->orderBy('name')->pluck('name')->implode(', ') : 'All classes';
        $subjects = $this->subject_ids ? Subject::whereIn('id', $this->subject_ids)->orderBy('name')->pluck('name')->implode(', ') : 'all subjects';

        return "{$classes} · {$subjects}";
    }

    /**
     * Exam types whose exams are averaged into one share of the term
     * result: an O-Level term's Activities of Integration, each a percentage,
     * together make the 20% however many there are.
     *
     * @var array<string, list<string>>
     */
    public const AVERAGED_TYPES = ['o_level' => ['ca']];

    public function isAveragedIn(?string $curriculum): bool
    {
        return in_array($this->type, self::AVERAGED_TYPES[$curriculum] ?? [], true);
    }

    /**
     * The exams' total weight for a curriculum. Averaged exams count once,
     * at the highest weight among them.
     *
     * @param  iterable<self>  $assessments
     */
    public static function totalWeight(iterable $assessments, ?string $curriculum): float
    {
        $total = 0.0;
        $averaged = 0.0;

        foreach ($assessments as $assessment) {
            if ($assessment->isAveragedIn($curriculum)) {
                $averaged = max($averaged, (float) $assessment->weight);
            } else {
                $total += (float) $assessment->weight;
            }
        }

        return $total + $averaged;
    }

    /**
     * Where a term's exam weights do not add up to 100% for a curriculum,
     * e.g. "Primary: 90%". Exams for every class count towards each
     * curriculum. A curriculum whose exams all have no weight is fine:
     * they then count equally.
     *
     * @param  array<string, string>  $curricula  key => label
     * @return array<string, float> label => total weight
     */
    public static function weightProblems(int $schoolId, int $termId, array $curricula): array
    {
        $exams = static::where('school_id', $schoolId)->where('term_id', $termId)->get(['curriculum', 'type', 'weight']);
        $problems = [];

        foreach ($curricula as $key => $label) {
            $applies = $exams->filter(fn (self $a) => $a->appliesTo($key));
            $total = round(self::totalWeight($applies, $key), 2);

            if ($applies->isNotEmpty() && $total > 0 && abs($total - 100) > 0.01) {
                $problems[$label] = $total;
            }
        }

        return $problems;
    }

    /** Short names for each level, for exam lists. */
    public const CURRICULUM_SHORT = ['nursery' => 'Nursery', 'primary' => 'Primary', 'o_level' => 'O-Level', 'a_level' => 'A-Level'];

    /**
     * The exam's name, with its level when the school runs more than one,
     * so O-Level's and A-Level's "End of Term" can be told apart.
     */
    public function displayName(): string
    {
        return $this->curriculum && count(SchoolType::keys()) > 1
            ? $this->name.' ('.(self::CURRICULUM_SHORT[$this->curriculum] ?? $this->curriculum).')'
            : (string) $this->name;
    }

    /**
     * Labels for a list of exams, told apart when two share a name (two
     * "Continuous Assessment" tests): the date it was held, or a number.
     *
     * @param  iterable<self>  $assessments
     * @param  callable(self): string  $label
     * @return array<int, string> id => label
     */
    public static function distinctLabels(iterable $assessments, callable $label): array
    {
        $labels = [];

        foreach ($assessments as $assessment) {
            $labels[(int) $assessment->getKey()] = [$assessment, $label($assessment)];
        }

        $counts = array_count_values(array_column($labels, 1));
        $seen = [];

        return array_map(function (array $pair) use ($counts, &$seen): string {
            [$assessment, $text] = $pair;

            if ($counts[$text] < 2) {
                return $text;
            }

            $seen[$text] = ($seen[$text] ?? 0) + 1;

            return $text.' — '.($assessment->held_on ? 'held '.Carbon::parse($assessment->held_on)->format('j M') : 'no. '.$seen[$text]);
        }, $labels);
    }

    public function typeLabel(): string
    {
        return config('academics.assessment_types')[$this->type] ?? ucfirst((string) $this->type);
    }

    /** Short column heading for report cards: BOT, MOT, EOT, CA, Topics. */
    public function shortLabel(): string
    {
        if ($this->type === 'topics') {
            return 'Topics';
        }

        return in_array($this->type, ['bot', 'mot', 'eot', 'ca'], true)
            ? strtoupper($this->type)
            : mb_strimwidth($this->name, 0, 8, '…');
    }
}
