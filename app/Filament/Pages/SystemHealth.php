<?php

namespace App\Filament\Pages;

use App\Support\SystemHealth as Checks;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * The platform owner's check that the server is set up properly: email,
 * SMS, queue worker, scheduler, backups, debug mode and open errors, each
 * with what to do when it is not right (App\Support\SystemHealth).
 */
class SystemHealth extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static string|\UnitEnum|null $navigationGroup = 'Platform Management';

    protected static ?string $navigationLabel = 'System health';

    protected static ?int $navigationSort = 8;

    protected static ?string $title = 'System health';

    protected string $view = 'filament.pages.system-health';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $problems = collect(app(Checks::class)->checks())->where('status', 'danger')->count();

        return $problems > 0 ? (string) $problems : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    /**
     * @return list<array{label: string, status: 'ok'|'warning'|'danger', summary: string, fix: string|null}>
     */
    public function checks(): array
    {
        return app(Checks::class)->checks();
    }
}
