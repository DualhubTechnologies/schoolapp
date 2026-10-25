<?php

namespace App\Services\Stores;

use App\Models\FinanceCategory;
use App\Models\FinanceEntry;
use App\Models\StoreItem;
use App\Models\StoreMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Every change to what is in the stores, in one place: stock received
 * from a supplier (and, if wished, the expense for it), stock issued, and
 * the count corrected after checking the shelf. Each writes a line on the
 * item's stock card with the balance after it, and the store can never
 * go below zero.
 */
class StoreLedger
{
    /**
     * Stock delivered. With $expense (category id and payment method), the
     * cost is also recorded as a school expense, so Finance needs no second
     * entry.
     *
     * @param  array{finance_category_id: int, method: string}|null  $expense
     */
    public function receive(StoreItem $item, float $quantity, ?float $unitCost, string|Carbon $date, ?string $supplier = null, ?string $reference = null, ?array $expense = null, ?string $notes = null): StoreMovement
    {
        if ($quantity <= 0) {
            throw new RuntimeException('Enter how much was received.');
        }

        return DB::transaction(function () use ($item, $quantity, $unitCost, $date, $supplier, $reference, $expense, $notes) {
            $item = StoreItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();
            $total = $unitCost === null ? null : round($quantity * $unitCost, 2);
            $entry = null;

            if ($expense !== null) {
                if (! $total) {
                    throw new RuntimeException('Enter the price to record this as an expense.');
                }

                $category = FinanceCategory::query()
                    ->where('school_id', $item->school_id)
                    ->where('type', 'expense')
                    ->whereNull('system_key')
                    ->whereKey($expense['finance_category_id'])
                    ->firstOrFail();

                $entry = FinanceEntry::create([
                    'school_id' => $item->school_id,
                    'type' => 'expense',
                    'finance_category_id' => $category->getKey(),
                    'entry_date' => $date,
                    'amount' => $total,
                    'party' => $supplier,
                    'description' => 'Stores: '.$item->amount($quantity).' '.$item->name,
                    'method' => array_key_exists($expense['method'], FinanceEntry::METHODS) ? $expense['method'] : 'cash',
                    'reference' => $reference,
                ]);
            }

            $balance = (float) $item->quantity + $quantity;
            $item->update(array_filter(['quantity' => $balance, 'unit_cost' => $unitCost], fn ($value) => $value !== null));

            return $this->write($item, 'in', $date, $quantity, $balance, [
                'unit_cost' => $unitCost,
                'total_cost' => $total,
                'supplier' => $supplier,
                'reference' => $reference,
                'finance_entry_id' => $entry?->getKey(),
                'notes' => $notes,
            ]);
        });
    }

    /** Stock given out, to the kitchen, a department or a person. */
    public function issue(StoreItem $item, float $quantity, string $issuedTo, string|Carbon $date, ?string $reference = null, ?string $notes = null): StoreMovement
    {
        if ($quantity <= 0) {
            throw new RuntimeException('Enter how much was issued.');
        }

        return DB::transaction(function () use ($item, $quantity, $issuedTo, $date, $reference, $notes) {
            $item = StoreItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();

            if ($quantity > (float) $item->quantity) {
                throw new RuntimeException('Only '.$item->amount().' of '.$item->name.' is in the store.');
            }

            $balance = (float) $item->quantity - $quantity;
            $item->update(['quantity' => $balance]);

            return $this->write($item, 'out', $date, $quantity, $balance, [
                'issued_to' => $issuedTo,
                'reference' => $reference,
                'unit_cost' => $item->unit_cost,
                'total_cost' => $item->unit_cost === null ? null : round($quantity * (float) $item->unit_cost, 2),
                'notes' => $notes,
            ]);
        });
    }

    /** The shelf was counted: the store now holds $counted. Returns null when nothing changed. */
    public function count(StoreItem $item, float $counted, string|Carbon $date, ?string $notes = null): ?StoreMovement
    {
        if ($counted < 0) {
            throw new RuntimeException('The count cannot be below zero.');
        }

        return DB::transaction(function () use ($item, $counted, $date, $notes) {
            $item = StoreItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();
            $difference = round($counted - (float) $item->quantity, 2);

            if ($difference == 0.0) {
                return null;
            }

            $item->update(['quantity' => $counted]);

            return $this->write($item, 'count', $date, $difference, $counted, ['notes' => $notes]);
        });
    }

    /**
     * @param  array<string, mixed>  $details
     */
    protected function write(StoreItem $item, string $type, string|Carbon $date, float $quantity, float $balance, array $details): StoreMovement
    {
        return StoreMovement::create([
            'school_id' => $item->school_id,
            'store_item_id' => $item->getKey(),
            'type' => $type,
            'moved_on' => $date,
            'quantity' => $quantity,
            'balance_after' => $balance,
            'recorded_by' => auth()->user()?->name,
            ...$details,
        ]);
    }
}
