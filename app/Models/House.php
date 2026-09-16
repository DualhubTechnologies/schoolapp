<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property int|null $capacity
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['school_id', 'name', 'capacity', 'is_active'])]
class House extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    // ── Capacity helpers ──

    public function isUnlimited(): bool
    {
        return is_null($this->capacity);
    }

    public function isFull(): bool
    {
        if ($this->isUnlimited()) {
            return false;
        }

        return $this->students()->count() >= $this->capacity;
    }

    public function availableSlots(): ?int
    {
        if ($this->isUnlimited()) {
            return null; // unlimited
        }

        return max(0, $this->capacity - $this->students()->count());
    }
}