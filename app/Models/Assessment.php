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

    public function typeLabel(): string
    {
        return config('academics.assessment_types')[$this->type] ?? ucfirst((string) $this->type);
    }

    /** Short column heading for report cards: BOT, MOT, EOT, CA. */
    public function shortLabel(): string
    {
        return in_array($this->type, ['bot', 'mot', 'eot', 'ca'], true)
            ? strtoupper($this->type)
            : mb_strimwidth($this->name, 0, 8, '…');
    }
}
