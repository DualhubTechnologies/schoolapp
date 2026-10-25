<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something the school stores keep and use up: posho, beans, sugar,
 * stationery, soap. The quantity only changes through StoreLedger
 * (receiving, issuing, counting), which writes a StoreMovement each time.
 *
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property string $category
 * @property string $unit
 * @property string $quantity
 * @property string|null $reorder_level
 * @property string|null $unit_cost
 * @property bool $is_active
 */
class StoreItem extends Model
{
    use Auditable;

    protected $fillable = ['school_id', 'name', 'category', 'unit', 'quantity', 'reorder_level', 'unit_cost', 'is_active'];

    public const CATEGORIES = [
        'food' => 'Food & provisions',
        'kitchen' => 'Kitchen & fuel',
        'stationery' => 'Stationery & printing',
        'cleaning' => 'Cleaning & toiletries',
        'teaching' => 'Teaching & laboratory',
        'repairs' => 'Building & repairs',
        'sports' => 'Sports',
        'uniform' => 'Uniforms & requirements',
        'other' => 'Other',
    ];

    public const UNITS = ['kg', 'bags', 'litres', 'jerrycans', 'pieces', 'packets', 'boxes', 'reams', 'cartons', 'bars', 'trays', 'bundles', 'pairs', 'sets'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'reorder_level' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<StoreMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StoreMovement::class);
    }

    public function isLow(): bool
    {
        return $this->reorder_level !== null && (float) $this->quantity <= (float) $this->reorder_level;
    }

    /** What is in the store now is worth, at the last price paid. */
    public function value(): ?float
    {
        return $this->unit_cost === null ? null : round((float) $this->quantity * (float) $this->unit_cost, 2);
    }

    /** One of the unit: "bag" for bags, "box" for boxes, "kg" for kg. */
    public function singularUnit(): string
    {
        return match (true) {
            str_ends_with($this->unit, 'xes') => substr($this->unit, 0, -2),
            str_ends_with($this->unit, 's') && strlen($this->unit) > 3 => substr($this->unit, 0, -1),
            default => $this->unit,
        };
    }

    /** "12.5 kg", "40 bags" */
    public function amount(float|string|null $quantity = null): string
    {
        $quantity = (float) ($quantity ?? $this->quantity);

        return rtrim(rtrim(number_format($quantity, 2), '0'), '.').' '.$this->unit;
    }
}
