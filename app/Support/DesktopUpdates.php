<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * The Windows app keeping itself up to date (NativePHP's updater, from
 * the newest GitHub release): it checks on start and every few hours,
 * downloads a new version in the background, and the top bar then offers
 * "Restart to update". NativePHP's classes exist only in the Windows
 * build, so they are named here, not imported.
 */
class DesktopUpdates
{
    public const READY = 'update.ready';

    protected const UPDATER = 'Native\\Desktop\\Facades\\AutoUpdater';

    public static function listen(): void
    {
        Event::listen('Native\\Desktop\\Events\\AutoUpdater\\UpdateDownloaded', function (object $event): void {
            DB::table('app_state')->updateOrInsert(['key' => self::READY], ['value' => (string) ($event->version ?? ''), 'updated_at' => now()]);
        });
    }

    /** The version this app is. */
    public static function current(): ?string
    {
        $version = config('nativephp.version');

        return is_string($version) && $version !== '' ? $version : null;
    }

    /** A newer version downloaded and ready to install, if any. */
    public static function ready(): ?string
    {
        if (! Edition::isDesktop()) {
            return null;
        }

        $ready = DB::table('app_state')->where('key', self::READY)->value('value');
        $current = static::current();

        return is_string($ready) && $ready !== '' && $current !== null && version_compare($ready, $current, '>') ? $ready : null;
    }

    public static function check(): bool
    {
        return static::call('checkForUpdates');
    }

    /** Closes SchoolHub, installs the new version and opens it again. */
    public static function install(): bool
    {
        return static::call('quitAndInstall');
    }

    protected static function call(string $method): bool
    {
        if (! Edition::isDesktop() || ! class_exists(self::UPDATER)) {
            return false;
        }

        $updater = self::UPDATER;

        try {
            $updater::$method();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
