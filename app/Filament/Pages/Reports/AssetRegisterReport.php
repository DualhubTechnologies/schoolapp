<?php

namespace App\Filament\Pages\Reports;

use App\Models\Asset;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * The asset register on paper: every asset with its tag, place,
 * condition and cost, grouped by category or by where it is, with the
 * totals - for the board, auditors and the end-of-year check.
 */
class AssetRegisterReport extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $title = 'Asset Register';

    protected static ?string $slug = 'reports/assets';

    protected string $view = 'filament.pages.reports.assets';

    /** category | location */
    public string $groupBy = 'category';

    /** in_use (default) | repair | gone | all */
    public string $show = 'in_use';

    public const SHOW = [
        'in_use' => 'In use',
        'repair' => 'Needing repair or broken',
        'gone' => 'Lost or disposed of',
        'all' => 'Everything on the register',
    ];

    public static function about(): string
    {
        return 'Everything the school owns, with tag numbers, where it is, condition and cost, by category or by place.';
    }

    public function coverage(): string
    {
        return (self::SHOW[$this->show] ?? 'In use').' · by '.($this->groupBy === 'location' ? 'place' : 'category').' · '.now()->format('j M Y');
    }

    /** @return Collection<int, Asset> */
    protected function assets(): Collection
    {
        return Asset::where('school_id', auth()->user()?->school_id)
            ->when($this->show === 'in_use', fn ($q) => $q->whereNotIn('condition', ['lost', 'disposed']))
            ->when($this->show === 'repair', fn ($q) => $q->whereIn('condition', ['poor', 'broken']))
            ->when($this->show === 'gone', fn ($q) => $q->whereIn('condition', ['lost', 'disposed']))
            ->orderBy('tag_seq')
            ->get();
    }

    /**
     * Groups of assets, each with its totals.
     *
     * @return array<int, array{name: string, assets: Collection<int, Asset>, items: int, cost: float}>
     */
    public function groups(): array
    {
        return $this->assets()
            ->groupBy(fn (Asset $asset): string => $this->groupBy === 'location'
                ? ($asset->location ?: 'Place not recorded')
                : (Asset::CATEGORIES[$asset->category] ?? 'Other'))
            ->sortKeys()
            ->map(fn (Collection $assets, string $name): array => [
                'name' => $name,
                'assets' => $assets,
                'items' => (int) $assets->sum('quantity'),
                'cost' => (float) $assets->sum('cost'),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, int> condition label => items */
    public function byCondition(): array
    {
        return $this->assets()
            ->groupBy('condition')
            ->map(fn (Collection $assets): int => (int) $assets->sum('quantity'))
            ->sortKeysUsing(fn (string $a, string $b): int => array_search($a, array_keys(Asset::CONDITIONS), true) <=> array_search($b, array_keys(Asset::CONDITIONS), true))
            ->mapWithKeys(fn (int $items, string $condition): array => [Asset::CONDITIONS[$condition] ?? $condition => $items])
            ->all();
    }

    public function sheet(): array
    {
        $rows = [];

        foreach ($this->groups() as $group) {
            foreach ($group['assets'] as $asset) {
                $rows[] = [
                    $asset->tag_no, $asset->name, Asset::CATEGORIES[$asset->category] ?? $asset->category, $asset->quantity,
                    $asset->location, Asset::CONDITIONS[$asset->condition] ?? $asset->condition, $asset->assigned_to,
                    $asset->serial_no, $asset->acquired_on?->format('j M Y'), $asset->cost === null ? null : (float) $asset->cost,
                ];
            }
        }

        $rows[] = ['Total', '', '', array_sum(array_column($rows, 3)), '', '', '', '', '', array_sum(array_map(fn (array $r): float => (float) $r[9], $rows))];

        return [
            ['Tag', 'Item', 'Category', 'Qty', 'Where', 'Condition', 'Looked after by', 'Serial no.', 'Acquired', 'Cost (UGX)'],
            $rows,
        ];
    }
}
