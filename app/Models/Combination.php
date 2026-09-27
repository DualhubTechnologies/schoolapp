<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An A-Level combination: three principal subjects plus a subsidiary
 * (General Paper is taken by everyone on top).
 */
class Combination extends Model
{
    protected $fillable = [
        'school_id',
        'name',
        'description',
        'subsidiary_subject_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'combination_subject');
    }

    public function subsidiary(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subsidiary_subject_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /** "PCM / SICT" */
    public function label(): string
    {
        return $this->name.($this->subsidiary ? ' / '.$this->subsidiary->label() : '');
    }
}
