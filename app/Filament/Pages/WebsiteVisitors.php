<?php

namespace App\Filament\Pages;

use App\Models\SiteVisit;
use App\Support\PublicSite;
use App\Support\VisitorLocation;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/**
 * Who visits the public website (home, About, Features, Pricing, Help,
 * Contact): how many, from which countries and cities, which pages, how
 * they arrived and on what device. Platform owner only. Recorded by
 * App\Support\SiteVisits without keeping any IP address.
 */
class WebsiteVisitors extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|\UnitEnum|null $navigationGroup = 'Platform Management';

    protected static ?string $navigationLabel = 'Website visitors';

    protected static ?int $navigationSort = 9;

    protected static ?string $title = 'Website visitors';

    protected string $view = 'filament.pages.website-visitors';

    public const PERIODS = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 365 => 'Last 12 months'];

    #[Url]
    public int $days = 30;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }

    public function updatedDays(): void
    {
        $this->days = array_key_exists($this->days, self::PERIODS) ? $this->days : 30;
    }

    public function from(): CarbonInterface
    {
        return today()->subDays(max(1, $this->days) - 1);
    }

    /**
     * @return array{visits: int, visitors: int, today: int, today_visitors: int, countries: int}
     */
    public function totals(): array
    {
        $period = SiteVisit::where('visited_on', '>=', $this->from()->toDateString());
        $today = SiteVisit::where('visited_on', today()->toDateString());

        return [
            'visits' => (clone $period)->count(),
            'visitors' => (clone $period)->distinct()->count('visitor'),
            'today' => (clone $today)->count(),
            'today_visitors' => (clone $today)->distinct()->count('visitor'),
            'countries' => (clone $period)->whereNotNull('country_code')->distinct()->count('country_code'),
        ];
    }

    /**
     * Visits and visitors for each day of the period, oldest first, with
     * empty days included so the chart has no gaps.
     *
     * @return list<array{date: CarbonInterface, visits: int, visitors: int}>
     */
    public function daily(): array
    {
        $rows = SiteVisit::where('visited_on', '>=', $this->from()->toDateString())
            ->selectRaw('visited_on, COUNT(*) as visits, COUNT(DISTINCT visitor) as visitors')
            ->groupBy('visited_on')
            ->get()
            ->keyBy(fn (SiteVisit $row) => $row->visited_on->toDateString());

        $days = [];

        for ($date = $this->from(); $date->lte(today()); $date = $date->copy()->addDay()) {
            $row = $rows->get($date->toDateString());
            $days[] = ['date' => $date, 'visits' => (int) ($row?->getAttribute('visits') ?? 0), 'visitors' => (int) ($row?->getAttribute('visitors') ?? 0)];
        }

        return $days;
    }

    /**
     * The most common values of one column in the period, with visits and
     * unique visitors, most visited first.
     *
     * @param  list<string>  $columns
     * @return list<array{label: string, code: string|null, visits: int, visitors: int}>
     */
    public function top(array $columns, int $limit = 10): array
    {
        $rows = SiteVisit::where('visited_on', '>=', $this->from()->toDateString())
            ->whereNotNull($columns[0])
            ->select($columns)
            ->selectRaw('COUNT(*) as visits, COUNT(DISTINCT visitor) as visitors')
            ->groupBy($columns)
            ->orderByDesc('visits')
            ->limit($limit)
            ->get();

        $top = [];

        foreach ($rows as $row) {
            $top[] = [
                'label' => $this->label($columns, $row),
                'code' => $columns[0] === 'country_code' ? $row->country_code : null,
                'visits' => (int) $row->getAttribute('visits'),
                'visitors' => (int) $row->getAttribute('visitors'),
            ];
        }

        return $top;
    }

    /** @param  list<string>  $columns */
    protected function label(array $columns, SiteVisit $row): string
    {
        return match ($columns[0]) {
            'country_code' => (string) ($row->country ?: $row->country_code),
            'city' => $row->city.($row->country ? ', '.$row->country : ''),
            'path' => $row->path === '/' ? 'Home page' : (PublicSite::PAGES[ltrim($row->path, '/')][0] ?? $row->path),
            'source' => SiteVisit::SOURCES[$row->source] ?? ucfirst($row->source),
            'device' => SiteVisit::DEVICES[$row->device] ?? ucfirst($row->device),
            default => (string) $row->getAttribute($columns[0]),
        };
    }

    /** Visits whose place could not be worked out (no database yet, or a private address). */
    public function unlocated(): int
    {
        return SiteVisit::where('visited_on', '>=', $this->from()->toDateString())->whereNull('country_code')->count();
    }

    public function locationReady(): bool
    {
        return app(VisitorLocation::class)->isAvailable();
    }

    public function locationKeySet(): bool
    {
        return filled(config('services.maxmind.license_key')) && filled(config('services.maxmind.account_id'));
    }
}
