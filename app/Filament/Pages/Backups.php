<?php

namespace App\Filament\Pages;

use App\Console\Commands\BackupRun;
use App\Support\Edition;
use App\Support\Modules;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The Windows app's backups: everything lives on one computer, so the
 * school makes copies here and saves them somewhere else (a flash disk,
 * another computer). Backups are made nightly as well (backup:run).
 *
 * Only in the Windows app: online, the server's backups hold every
 * school's data and are the platform owner's job.
 */
class Backups extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Backups';

    protected string $view = 'filament.pages.backups';

    public static function canAccess(): bool
    {
        return Edition::isDesktop() && Modules::allows('settings');
    }

    /**
     * Newest first, with the database and files of one backup together.
     *
     * @return list<array{name: string, kind: string, size: string, made: Carbon}>
     */
    public function backups(): array
    {
        $files = File::glob(BackupRun::folder().'/schoolhub-*') ?: [];
        usort($files, fn (string $a, string $b): int => File::lastModified($b) <=> File::lastModified($a));

        return array_map(fn (string $file): array => [
            'name' => basename($file),
            'kind' => str_contains(basename($file), '-db-') ? 'School data' : 'Photos, logos and signatures',
            'size' => $this->size(File::size($file)),
            'made' => Carbon::createFromTimestamp(File::lastModified($file))->timezone(config('app.timezone')),
        ], $files);
    }

    public function download(string $name): ?BinaryFileResponse
    {
        abort_unless(static::canAccess(), 403);

        // Only a file of this folder, by its plain name.
        $path = BackupRun::folder().'/'.basename($name);

        if (! str_starts_with(basename($name), 'schoolhub-') || ! File::exists($path)) {
            Notification::make()->title('That backup is no longer there')->danger()->send();

            return null;
        }

        return response()->download($path);
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('backupNow')
                ->label('Back up now')
                ->icon('heroicon-o-arrow-down-on-square-stack')
                ->action(function (): void {
                    $ok = Artisan::call('backup:run') === 0;

                    $ok
                        ? Notification::make()->title('Backup made')->body('Now download it and save it on a flash disk or another computer.')->success()->send()
                        : Notification::make()->title('The backup did not finish')->body(trim(Artisan::output()) ?: 'Please try again.')->danger()->send();
                }),
        ];
    }

    protected function size(int $bytes): string
    {
        return $bytes >= 1048576 ? number_format($bytes / 1048576, 1).' MB' : number_format(max(1, $bytes / 1024)).' KB';
    }
}
