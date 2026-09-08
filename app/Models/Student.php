<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'user_id',
        'guardian_id',
        'school_class_id',
        'section_id',
        'admission_no',
        'name',
        'email',
        'phone',
        'date_of_birth',
        'gender',
        'admission_date',
        'photo',
        'address',
        'medical_notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'admission_date' => 'date',
        ];
    }

    public const STATUSES = [
        'active' => 'Active',
        'graduated' => 'Graduated',
        'withdrawn' => 'Withdrawn',
        'transferred' => 'Transferred',
    ];

    public const GENDERS = [
        'male' => 'Male',
        'female' => 'Female',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function hasLogin(): bool
    {
        return $this->user_id !== null;
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }
}