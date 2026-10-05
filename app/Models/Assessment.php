<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One exam or piece of continuous assessment in a term, e.g. "Mid-Term
 * Examination" or "Activity of Integration 1". Its weight is its share of
 * the term result.
 */
class Assessment extends Model
{
    protected $fillable = [
        'school_id',
        'term_id',
        'name',
        'type',
        'curriculum',
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
        $exams = static::where('school_id', $schoolId)->where('term_id', $termId)->get(['curriculum', 'weight']);
        $problems = [];

        foreach ($curricula as $key => $label) {
            $applies = $exams->filter(fn (self $a) => $a->appliesTo($key));
            $total = round((float) $applies->sum(fn (self $a) => (float) $a->weight), 2);

            if ($applies->isNotEmpty() && $total > 0 && abs($total - 100) > 0.01) {
                $problems[$label] = $total;
            }
        }

        return $problems;
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
