<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\PlacedInMenu;
use App\Support\Edition;
use App\Support\Modules;
use App\Support\PageTimings;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Windows app: the slowest pages and actions on this computer
 * (PageTimings), to send to SchoolHub on WhatsApp so they can be made
 * quicker.
 */
class SpeedCheck extends Page
{
    use PlacedInMenu;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $title = 'Speed check';

    protected static ?string $slug = 'speed-check';

    protected string $view = 'filament.pages.speed-check';

    public static function canAccess(): bool
    {
        return Edition::isDesktop() && auth()->user()?->school_id !== null && Modules::hasFullAccess(auth()->user());
    }

    /** @return list<array{page: string, count: int, average: int, slowest: int, queries: int}> */
    public function rows(): array
    {
        return PageTimings::slowest();
    }

    public function whatsappUrl(): string
    {
        return 'https://wa.me/'.config('contact.whatsapp').'?text='.rawurlencode(PageTimings::report());
    }

    public function clear(): void
    {
        PageTimings::clear();
        Notification::make()->title('Cleared')->body('Use SchoolHub as usual for a day, then look here again.')->success()->send();
    }
}
