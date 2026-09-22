<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How much the school plans to receive or spend under one category in
 * one term.
 */
class BudgetLine extends Model
{
    protected $fillable = [
        'school_id',
        'term_id',
        'finance_category_id',
        'amount',
        'notes',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'finance_category_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}
