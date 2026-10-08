<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mark → grade table for one curriculum and purpose (subject grades, or
 * A-Level principal / subsidiary grades).
 */
class GradingScale extends Model
{
    protected $fillable = [
        'school_id',
        'curriculum',
        'purpose',
        'name',
    ];

    public const PURPOSES = [
        'subject' => 'Subject grades',
        'principal' => 'A-Level principal subjects',
        'subsidiary' => 'A-Level subsidiary subjects',
        'paper' => 'A-Level paper grades (UNEB)',
    ];

    public function bands(): HasMany
    {
        return $this->hasMany(GradingBand::class)->orderByDesc('min_score');
    }

    /**
     * The band a percentage falls in. Scores are compared to 2 decimal
     * places; a score in a gap between bands takes the band below it.
     */
    public function bandFor(?float $score): ?GradingBand
    {
        if ($score === null) {
            return null;
        }

        $score = round($score, 2);

        return $this->bands->first(fn (GradingBand $b) => $score >= (float) $b->min_score);
    }
}
