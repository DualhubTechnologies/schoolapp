<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subject the school teaches, within one curriculum.
 */
class Subject extends Model
{
    protected $fillable = [
        'school_id',
        'curriculum',
        'name',
        'short_name',
        'code',
        'category',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public const CATEGORIES = [
        'standard' => 'Standard',
        'core' => 'Core (counts in primary aggregate)',
        'principal' => 'A-Level principal',
        'subsidiary' => 'A-Level subsidiary',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'class_subject')
            ->withPivot(['is_compulsory', 'teacher_id'])
            ->withTimestamps();
    }

    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class);
    }

    public function label(): string
    {
        return $this->short_name ?: $this->name;
    }
}
