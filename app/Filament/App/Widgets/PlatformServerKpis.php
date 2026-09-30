<?php

namespace App\Filament\App\Widgets;

use App\Filament\Pages\SystemHealth;
use App\Support\ServerStats;

/**
 * Super Admin: how hard the server is working (CPU, memory, disk and the
 * database), under today's activity. Each card opens System health, which
 * has every reading and what to do when one is high.
 */
class PlatformServerKpis extends KpiCards
{
    protected static ?int $sort = -3;

    /** The cards are coloured by status: fine, worth watching, too high. */
    protected const TONES = ['ok' => 'emerald', 'warning' => 'amber', 'danger' => 'rose'];

    protected const ICONS = [
        'load' => 'heroicon-o-cpu-chip',
        'memory' => 'heroicon-o-circle-stack',
        'disk' => 'heroicon-o-server-stack',
        'database' => 'heroicon-o-bolt',
    ];

    public static function canView(): bool
    {
        return (auth()->user()?->hasRole('Super Admin') ?? false) && app(ServerStats::class)->readings() !== [];
    }

    public function cards(): array
    {
        $url = SystemHealth::getUrl(panel: 'admin');

        $cards = [];

        foreach (app(ServerStats::class)->readings() as $r) {
            if (! isset(self::ICONS[$r['key']])) {
                continue;
            }

            $cards[] = static::card(
                self::TONES[$r['status']],
                self::ICONS[$r['key']],
                $r['label'],
                $r['value'],
                $r['status'] === 'ok' ? $r['detail'] : ($r['status'] === 'danger' ? 'Too high — open System health' : 'Worth watching — open System health'),
                $url,
                $r['percent'],
            );
        }

        return $cards;
    }
}
