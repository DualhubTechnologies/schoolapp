<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of a grading scale: e.g. D1, 80–100%, value 1, "Distinction".
 */
class GradingBand extends Model
{
    protected $fillable = [
        'grading_scale_id',
        'grade',
        'min_score',
        'max_score',
        'value',
        'descriptor',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'value' => 'decimal:2',
        ];
    }

    public function scale(): BelongsTo
    {
        return $this->belongsTo(GradingScale::class, 'grading_scale_id');
    }
}
