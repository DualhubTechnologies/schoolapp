<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\PlacedInMenu;
use App\Models\SyncDevice;
use App\Services\Sync\SyncServer;
use App\Support\Edition;
use App\Support\Modules;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Online: the school's Windows computers that sync with its account. A
 * School Admin makes a one-hour link code to type into the Windows app
 * (Settings → Sync with online), and can remove a computer, which stops
 * it syncing straight away.
 */
class WindowsApp extends Page
{
    use PlacedInMenu;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static ?string $title = 'Windows app';

    protected static ?string $slug = 'windows-app';

    protected string $view = 'filament.pages.windows-app';

    public ?string $linkCode = null;

    public static function canAccess(): bool
    {
        return Edition::isServer() && auth()->user()?->school_id !== null && Modules::hasFullAccess(auth()->user());
    }

    /** @return Collection<int, SyncDevice> */
    public function computers(): Collection
    {
        return SyncDevice::where('school_id', auth()->user()?->school_id)->whereNotNull('token')->orderByDesc('last_synced_at')->get();
    }

    public function makeCode(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->linkCode = app(SyncServer::class)->makeLinkCode(auth()->user()->school, auth()->user()->name);
    }

    public function remove(int $device): void
    {
        abort_unless(static::canAccess(), 403);

        SyncDevice::where('school_id', auth()->user()?->school_id)->whereKey($device)->delete();
        Notification::make()->title('Computer removed')->body('It no longer syncs. What is on it stays there.')->success()->send();
    }
}
