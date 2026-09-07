<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeductionType extends Model
{
    protected $fillable = [
        'school_id',
        'name',
        'is_statutory',
        'calculation_method',
        'default_rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_statutory' => 'boolean',
            'default_rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function staffDeductions(): HasMany
    {
        return $this->hasMany(StaffDeduction::class);
    }
}
