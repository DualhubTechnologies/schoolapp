<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A medicine the sick bay keeps, with how much is in stock. Optional: a
 * school that does not track stock simply has none, and the nurse writes
 * what was given in words.
 *
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property string $unit
 * @property int $stock
 * @property int|null $reorder_level
 * @property bool $is_active
 */
class SickBayMedicine extends Model
{
    use Auditable;

    protected $fillable = ['school_id', 'name', 'unit', 'stock', 'reorder_level', 'is_active'];

    protected function casts(): array
    {
        return ['stock' => 'integer', 'reorder_level' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return HasMany<SickBayDispense, $this> */
    public function dispensed(): HasMany
    {
        return $this->hasMany(SickBayDispense::class);
    }

    public function isLow(): bool
    {
        return $this->reorder_level !== null && $this->stock <= $this->reorder_level;
    }
}
