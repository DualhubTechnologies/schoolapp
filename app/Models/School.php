<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
        'slug',
        'motto',
        'description',
        'school_type',
        'unique_code',
        'email',
        'nssf_employer_number',
        'tin_number',
        'phone',
        'website',
        'address',
        'city',
        'country',
        'logo',
        'hm_signature',
        'timezone',
        'currency',
        'status',
    ];

    /**
     * A school is one of these, chosen once when it signs up.
     *
     * This is identity rather than configuration: report cards, grading
     * and class levels all branch on it.
     */
    public const TYPE_PRIMARY = 'primary';

    public const TYPE_SECONDARY = 'secondary';

    public const TYPES = [
        self::TYPE_PRIMARY => 'Primary School',
        self::TYPE_SECONDARY => 'Secondary School',
    ];

    // ── Relationships ──

    public function classLevels(): HasMany
    {
        return $this->hasMany(ClassLevel::class)->orderBy('sort_order');
    }

    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    // ── Type helpers ──

    public function isPrimary(): bool
    {
        return $this->school_type === self::TYPE_PRIMARY;
    }

    public function isSecondary(): bool
    {
        return $this->school_type === self::TYPE_SECONDARY;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->school_type] ?? '—';
    }
}
