<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AllowanceType extends Model
{
    protected $fillable = [
        'school_id',
        'name',
        'is_taxable',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function staffAllowances(): HasMany
    {
        return $this->hasMany(StaffAllowance::class);
    }
}
