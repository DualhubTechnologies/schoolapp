<?php

namespace App\Filament\App\Widgets;

use App\Filament\Widgets\Concerns\SchoolScoped;
use Filament\Widgets\Widget;

/**
 * A row of headline figures. Each dashboard extends this and lists its
 * own cards; they all share one design so every dashboard feels the same.
 */
abstract class KpiCards extends Widget
{
    use SchoolScoped;

    protected static ?int $sort = -5;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.app.widgets.kpi-cards';

    protected static bool $isLazy = false;

    /**
     * @return list<array{tone: string, icon: string, label: string, value: string, sub?: ?string, url?: ?string, progress?: ?int, trend?: 'up'|'down'|null}>
     */
    abstract public function cards(): array;

    /** Tones map to a colour set in the stylesheet: blue, emerald, amber, violet, rose, sky, teal, indigo. */
    protected static function card(string $tone, string $icon, string $label, string $value, ?string $sub = null, ?string $url = null, ?int $progress = null, ?string $trend = null): array
    {
        return compact('tone', 'icon', 'label', 'value', 'sub', 'url', 'progress', 'trend');
    }

    protected static function percent(float $part, float $whole): ?int
    {
        return $whole > 0 ? (int) min(100, round($part / $whole * 100)) : null;
    }

    /** "42%", or "Under 1%" when something was done but it rounds to nothing. */
    protected static function percentText(float $part, float $whole): string
    {
        $pct = static::percent($part, $whole) ?? 0;

        return $pct === 0 && $part > 0 ? 'Under 1%' : "{$pct}%";
    }
}
