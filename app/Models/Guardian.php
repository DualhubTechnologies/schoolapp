<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guardian extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'user_id',
        'name',
        'phone',
        'alt_phone',
        'email',
        'relationship',
        'occupation',
        'national_id',
        'address',
    ];

    public const RELATIONSHIPS = [
        'father' => 'Father',
        'mother' => 'Mother',
        'guardian' => 'Guardian',
        'other' => 'Other',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function hasLogin(): bool
    {
        return $this->user_id !== null;
    }
}