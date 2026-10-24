<?php

namespace App\Filament\Pages;

use App\Services\Backups\GoogleDrive;
use App\Support\ServerStats;
use App\Support\SystemHealth as Checks;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

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
     * Off-site backups: connect the owner's Google Drive, run a backup
     * (and copy) straight away, or stop copying.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('connectGoogleDrive')
                ->label(GoogleDrive::isConnected() ? 'Connect a different Google Drive' : 'Connect Google Drive')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color(GoogleDrive::isConnected() ? 'gray' : 'primary')
                ->visible(fn (): bool => GoogleDrive::isConfigured())
                ->url(fn (): string => route('filament.admin.google-drive.connect')),
            Action::make('backUpNow')
                ->label('Back up now')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Back up now?')
                ->modalDescription(fn (): string => GoogleDrive::isConnected()
                    ? 'Backs up the database and uploaded files on this server, then sends an encrypted copy to '.GoogleDrive::account().'. It can take a few minutes.'
                    : 'Backs up the database and uploaded files on this server. It can take a few minutes.')
                ->modalSubmitActionLabel('Back up now')
                ->action(function (): void {
                    $before = Cache::get(GoogleDrive::LAST_COPY);
                    $failed = Artisan::call('backup:run') !== 0;
                    $copied = Cache::get(GoogleDrive::LAST_COPY) !== $before;

                    if ($failed) {
                        Notification::make()->title('Backup failed')->body('See Error reports for why.')->danger()->send();
                    } elseif (GoogleDrive::isConnected() && ! $copied) {
                        Notification::make()->title('Backed up on this server; the Google Drive copy failed')->body('See Error reports for why.')->warning()->send();
                    } else {
                        Notification::make()->title('Backup done')->body(GoogleDrive::isConnected() ? 'Saved on this server and copied to Google Drive.' : 'Saved on this server.')->success()->send();
                    }
                }),
            Action::make('disconnectGoogleDrive')
                ->label('Disconnect Google Drive')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->link()
                ->visible(fn (): bool => GoogleDrive::isConnected())
                ->requiresConfirmation()
                ->modalDescription(fn (): string => 'Backups will stop being copied to '.GoogleDrive::account().'. Copies already there stay in the Drive.')
                ->action(function (GoogleDrive $drive): void {
                    $drive->disconnect();
                    Notification::make()->title('Google Drive disconnected')->success()->send();
                }),
        ];
    }

    /**
     * @return list<array{label: string, status: 'ok'|'warning'|'danger', summary: string, fix: string|null}>
     */
    public function checks(): array
    {
        return app(Checks::class)->checks();
    }

    /**
     * CPU, memory, swap, disk, database and uptime, read fresh on every
     * refresh (the panel refreshes itself every 30 seconds).
     *
     * @return list<array{key: string, label: string, status: 'ok'|'warning'|'danger', value: string, detail: string, percent: int|null, fix: string|null}>
     */
    public function serverReadings(): array
    {
        return app(ServerStats::class)->readings();
    }
}
