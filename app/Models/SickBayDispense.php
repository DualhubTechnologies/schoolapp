<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A medicine given at a sick bay visit. Giving it takes it out of stock;
 * changing or removing the line puts the difference back.
 *
 * @property int $id
 * @property int $sick_bay_visit_id
 * @property int $sick_bay_medicine_id
 * @property int $quantity
 * @property string|null $dosage
 */
class SickBayDispense extends Model
{
    protected $table = 'sick_bay_dispensed';

    protected $fillable = ['sick_bay_visit_id', 'sick_bay_medicine_id', 'quantity', 'dosage'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    protected static function booted(): void
    {
        static::created(fn (self $line) => $line->medicine?->decrement('stock', $line->quantity));
        static::deleted(fn (self $line) => $line->medicine?->increment('stock', $line->quantity));
        static::updated(function (self $line): void {
            if ($line->wasChanged('sick_bay_medicine_id')) {
                SickBayMedicine::whereKey($line->getOriginal('sick_bay_medicine_id'))->increment('stock', (int) $line->getOriginal('quantity'));
                $line->medicine?->decrement('stock', $line->quantity);
            } elseif ($line->wasChanged('quantity')) {
                $line->medicine?->decrement('stock', $line->quantity - (int) $line->getOriginal('quantity'));
            }
        });
    }

    /** @return BelongsTo<SickBayVisit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(SickBayVisit::class, 'sick_bay_visit_id');
    }

    /** @return BelongsTo<SickBayMedicine, $this> */
    public function medicine(): BelongsTo
    {
        return $this->belongsTo(SickBayMedicine::class, 'sick_bay_medicine_id');
    }
}
