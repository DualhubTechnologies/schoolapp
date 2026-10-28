<?php

namespace App\Support;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;

/**
 * The Windows app's own encryption key, kept in its data folder and made
 * on first run. The installer's APP_KEY is new with every build, so what
 * one version encrypted (the sync link's token) another could not read
 * ("The MAC is invalid") after an update; and every computer with the same
 * version would share it. This key stays through updates and is this
 * computer's alone.
 */
class DesktopKey
{
    public static function apply(): void
    {
        $key = static::read() ?? static::make();

        if ($key === null || $key === config('app.key')) {
            return;
        }

        config(['app.key' => $key]);

        // In case anything was encrypted with the installer's key already.
        app()->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');
    }

    public static function file(): string
    {
        return storage_path('app/install.key');
    }

    protected static function read(): ?string
    {
        $key = @file_get_contents(static::file());

        return is_string($key) && str_starts_with(trim($key), 'base64:') ? trim($key) : null;
    }

    /** A new key, or null when the data folder cannot be written (the installer's key is used then). */
    protected static function make(): ?string
    {
        $key = 'base64:'.base64_encode(Encrypter::generateKey((string) config('app.cipher')));

        @mkdir(dirname(static::file()), 0755, true);

        return @file_put_contents(static::file(), $key) === false ? null : $key;
    }
}
