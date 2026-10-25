<?php

namespace App\Filament\Pages\Reports;

use App\Models\StoreItem;
use App\Models\StoreMovement;
use App\Models\Term;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The stores over a period: for each item what was in the store at the
 * start, what came in, what was issued, what counting corrected and what
 * is left, with its value; then where the stock went (kitchen,
 * dormitories, departments). Built from the stock movements, so the
 * figures agree with each item's stock card.
 */
class StockReport extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $title = 'Stock Report';

    protected static ?string $slug = 'reports/stock';

    protected string $view = 'filament.pages.reports.stock';

    public string $from = '';

    public string $to = '';

    /** One kind of stock (food, stationery...), or all. */
    public string $category = '';

    public static function about(): string
    {
        return 'For a period: each store item\'s opening stock, received, issued, counted and closing stock with its value, and where it was issued.';
    }

    public function mount(): void
    {
        $this->setRange('term');
    }

    /** @return array<string, array{0: string, 1: string}> */
    protected function ranges(): array
    {
        $today = now()->toImmutable();
        $start = Term::current()?->start_date;

        return collect([
            'month' => $today->startOfMonth(),
            'term' => $start ? $start->toImmutable() : $today->startOfMonth(),
            'year' => $today->startOfYear(),
        ])->map(fn (CarbonImmutable $from): array => [$from->toDateString(), $today->toDateString()])->all();
    }

    public function setRange(string $range): void
    {
        [$this->from, $this->to] = $this->ranges()[$range] ?? $this->ranges()['term'];
    }

    public function range(): ?string
    {
        $match = array_search([$this->from, $this->to], $this->ranges(), true);

        return $match === false ? null : $match;
    }

    public function fromDate(): CarbonImmutable
    {
        return $this->date($this->from, now()->toImmutable()->startOfMonth());
    }

    public function toDate(): CarbonImmutable
    {
        return $this->date($this->to, now()->toImmutable());
    }

    protected function date(string $value, CarbonImmutable $fallback): CarbonImmutable
    {
        try {
            return $value !== '' ? CarbonImmutable::parse($value) : $fallback;
        } catch (Throwable) {
            return $fallback;
        }
    }

    public function coverage(): string
    {
        return trim(($this->category !== '' ? (StoreItem::CATEGORIES[$this->category] ?? '').' · ' : '')
            .$this->fromDate()->format('j M Y').' - '.$this->toDate()->format('j M Y'));
    }

    /**
     * One row per item, then the total value.
     *
     * @return array<int, array{item: StoreItem|null, label: string, kind: string, opening: float, received: float, issued: float, counted: float, closing: float, received_cost: float, issued_cost: float, value: float|null, low: bool}>
     */
    public function rows(): array
    {
        $schoolId = auth()->user()?->school_id;
        $from = $this->fromDate()->toDateString();
        $to = $this->toDate()->toDateString();

        $items = StoreItem::where('school_id', $schoolId)
            ->when($this->category !== '', fn ($q) => $q->where('category', $this->category))
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $movements = StoreMovement::where('school_id', $schoolId)
            ->whereIn('store_item_id', $items->modelKeys())
            ->whereDate('moved_on', '<=', $to)
            ->orderBy('moved_on')
            ->orderBy('id')
            ->get()
            ->groupBy('store_item_id');

        $rows = [];

        foreach ($items as $item) {
            /** @var Collection<int, StoreMovement> $mine */
            $mine = $movements->get($item->getKey(), collect());
            $before = $mine->filter(fn (StoreMovement $m): bool => $m->moved_on->toDateString() < $from);
            $during = $mine->filter(fn (StoreMovement $m): bool => $m->moved_on->toDateString() >= $from);

            // Not kept any more and nothing happened: leave it out.
            if (! $item->is_active && $during->isEmpty()) {
                continue;
            }

            $opening = (float) ($before->last()->balance_after ?? 0);
            $closing = (float) ($mine->last()->balance_after ?? 0);
            $price = $item->unit_cost === null ? null : (float) $item->unit_cost;

            $rows[] = [
                'item' => $item,
                'label' => $item->name,
                'kind' => 'item',
                'opening' => $opening,
                'received' => (float) $during->where('type', 'in')->sum('quantity'),
                'issued' => (float) $during->where('type', 'out')->sum('quantity'),
                'counted' => (float) $during->where('type', 'count')->sum('quantity'),
                'closing' => $closing,
                'received_cost' => (float) $during->where('type', 'in')->sum('total_cost'),
                'issued_cost' => (float) $during->where('type', 'out')->sum('total_cost'),
                'value' => $price === null ? null : round($closing * $price, 2),
                'low' => $item->reorder_level !== null && $closing <= (float) $item->reorder_level,
            ];
        }

        if ($rows !== []) {
            $rows[] = [
                'item' => null,
                'label' => 'Total',
                'kind' => 'total',
                'opening' => 0.0, 'received' => 0.0, 'issued' => 0.0, 'counted' => 0.0, 'closing' => 0.0,
                'received_cost' => array_sum(array_column($rows, 'received_cost')),
                'issued_cost' => array_sum(array_column($rows, 'issued_cost')),
                'value' => array_sum(array_map(fn (array $row): float => (float) $row['value'], $rows)),
                'low' => false,
            ];
        }

        return $rows;
    }

    /**
     * Where stock was issued in the period, the most first.
     *
     * @return array<int, array{to: string, issues: int, cost: float, items: string}>
     */
    public function issuedTo(): array
    {
        return StoreMovement::with('item')
            ->where('school_id', auth()->user()?->school_id)
            ->where('type', 'out')
            ->whereBetween('moved_on', [$this->fromDate()->toDateString(), $this->toDate()->toDateString()])
            ->when($this->category !== '', fn ($q) => $q->whereHas('item', fn ($q) => $q->where('category', $this->category)))
            ->get()
            ->groupBy(fn (StoreMovement $m): string => trim((string) $m->issued_to) ?: 'Not stated')
            ->map(fn (Collection $issues, string $to): array => [
                'to' => $to,
                'issues' => $issues->count(),
                'cost' => (float) $issues->sum('total_cost'),
                'items' => $issues->groupBy('store_item_id')
                    ->map(fn (Collection $lines): string => $lines->first()->item->amount($lines->sum('quantity')).' '.$lines->first()->item->name)
                    ->implode(', '),
            ])
            ->sortByDesc('cost')
            ->values()
            ->all();
    }

    public function sheet(): array
    {
        $qty = fn (array $row, string $key): string => $row['kind'] === 'total' ? '' : $row['item']->amount($row[$key]);

        return [
            ['Item', 'Category', 'Opening', 'Received', 'Issued', 'Counted (+/-)', 'Closing', 'Received cost (UGX)', 'Issued cost (UGX)', 'Value now (UGX)'],
            array_map(fn (array $row): array => [
                $row['label'],
                $row['kind'] === 'total' ? '' : (StoreItem::CATEGORIES[$row['item']->category] ?? ''),
                $qty($row, 'opening'), $qty($row, 'received'), $qty($row, 'issued'),
                $row['kind'] === 'total' || $row['counted'] == 0.0 ? '' : ($row['counted'] > 0 ? '+' : '').$row['item']->amount($row['counted']),
                $qty($row, 'closing'),
                $row['received_cost'], $row['issued_cost'], $row['value'],
            ], $this->rows()),
        ];
    }
}
