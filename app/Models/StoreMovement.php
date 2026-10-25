<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of a store item's stock card: received from a supplier (in),
 * issued to the kitchen, a department or a person (out), or corrected
 * after counting the shelf (count). Written only by StoreLedger, and never
 * changed afterwards.
 *
 * @property int $id
 * @property int $school_id
 * @property int $store_item_id
 * @property string $type
 * @property Carbon $moved_on
 * @property string $quantity
 * @property string $balance_after
 * @property string|null $unit_cost
 * @property string|null $total_cost
 * @property string|null $supplier
 * @property string|null $issued_to
 * @property string|null $reference
 * @property int|null $finance_entry_id
 * @property string|null $recorded_by
 * @property string|null $notes
 */
class StoreMovement extends Model
{
    public const TYPES = [
        'in' => 'Received',
        'out' => 'Issued',
        'count' => 'Stock count',
    ];

    protected $fillable = [
        'school_id', 'store_item_id', 'type', 'moved_on', 'quantity', 'balance_after', 'unit_cost', 'total_cost',
        'supplier', 'issued_to', 'reference', 'finance_entry_id', 'recorded_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'moved_on' => 'date',
            'quantity' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<StoreItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(StoreItem::class, 'store_item_id');
    }

    /** @return BelongsTo<FinanceEntry, $this> */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(FinanceEntry::class, 'finance_entry_id');
    }
}
