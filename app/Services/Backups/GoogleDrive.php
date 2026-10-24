<?php

namespace App\Services\Backups;

use App\Models\PlatformSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Off-site copies of the nightly backups in the platform owner's Google
 * Drive. Each file is encrypted on this server first (AES-256 with
 * BACKUP_PASSPHRASE, via openssl), so Google only ever holds files it
 * cannot read. Copies older than GOOGLE_DRIVE_KEEP_DAYS are deleted.
 *
 * Connecting is done once from System health (GoogleDriveController): the
 * owner signs in to Google and allows SchoolHub to manage the files it
 * creates (drive.file); nothing else in the Drive can be seen. The
 * refresh token is kept encrypted in platform_settings.
 *
 * To open a copy: openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000
 * -in FILE.enc -out FILE (it asks for the passphrase).
 */
class GoogleDrive
{
    public const REFRESH_TOKEN = 'google_drive.refresh_token';

    public const ACCOUNT = 'google_drive.account';

    public const FOLDER_ID = 'google_drive.folder_id';

    /** Cache key: time and files of the last copy to Google Drive. */
    public const LAST_COPY = 'backup:last-offsite';

    protected const SCOPES = 'openid email https://www.googleapis.com/auth/drive.file';

    /** Uploaded in pieces of this size (a multiple of 256 KB, as Google asks). */
    protected const CHUNK = 8 * 1024 * 1024;

    public static function isConfigured(): bool
    {
        return filled(config('services.google_drive.client_id')) && filled(config('services.google_drive.client_secret'));
    }

    public static function isConnected(): bool
    {
        return static::isConfigured() && filled(PlatformSetting::get(self::REFRESH_TOKEN));
    }

    public static function hasPassphrase(): bool
    {
        return strlen((string) config('services.google_drive.passphrase')) >= 12;
    }

    /** The Google account the copies go to, e.g. owner@gmail.com. */
    public static function account(): ?string
    {
        return PlatformSetting::get(self::ACCOUNT);
    }

    public static function redirectUri(): string
    {
        return route('filament.admin.google-drive.callback');
    }

    /** Google's page asking the owner to allow SchoolHub into their Drive. */
    public function authorizationUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google_drive.client_id'),
            'redirect_uri' => static::redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /**
     * Finish connecting with the code Google sends back: keep the refresh
     * token and the account's email address.
     *
     * @throws RuntimeException
     */
    public function connect(string $code): string
    {
        $response = $this->send(fn () => Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google_drive.client_id'),
            'client_secret' => config('services.google_drive.client_secret'),
            'redirect_uri' => static::redirectUri(),
            'grant_type' => 'authorization_code',
        ]));

        $refresh = (string) $response->json('refresh_token');

        if (! $response->successful() || $refresh === '') {
            throw new RuntimeException('Google did not connect the Drive ('.($response->json('error_description') ?: $response->json('error') ?: 'HTTP '.$response->status()).'). Please try again.');
        }

        // Google lets the owner untick Drive on its consent page; without it
        // every upload is refused (HTTP 403), so say so now.
        $granted = explode(' ', (string) $response->json('scope'));

        if (! in_array('https://www.googleapis.com/auth/drive.file', $granted, true)) {
            throw new RuntimeException('Google Drive was not allowed. Click Connect Google Drive again and, on Google\'s page, tick the box "See, edit, create and delete only the specific Google Drive files you use with this app" before Continue.');
        }

        $account = $this->emailFromIdToken((string) $response->json('id_token')) ?? 'your Google account';

        PlatformSetting::put(self::REFRESH_TOKEN, $refresh);
        PlatformSetting::put(self::ACCOUNT, $account);
        PlatformSetting::forget(self::FOLDER_ID);
        Cache::put($this->tokenCacheKey(), (string) $response->json('access_token'), max(60, (int) $response->json('expires_in', 3600) - 120));

        return $account;
    }

    /** Stop copying: Google forgets SchoolHub's access, and so does SchoolHub. */
    public function disconnect(): void
    {
        $refresh = PlatformSetting::get(self::REFRESH_TOKEN);

        if ($refresh) {
            try {
                Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/revoke', ['token' => $refresh]);
            } catch (ConnectionException) {
                // Forgotten here either way; the owner can also remove it in their Google account.
            }
        }

        PlatformSetting::forget(self::REFRESH_TOKEN, self::ACCOUNT, self::FOLDER_ID);
        Cache::forget($this->tokenCacheKey());
    }

    /**
     * Encrypt and upload these backup files, then delete copies in the
     * Drive older than the days kept. Returns the names uploaded.
     *
     * @param  list<string>  $paths
     * @return list<string>
     *
     * @throws RuntimeException
     */
    public function copy(array $paths): array
    {
        if (! static::isConnected()) {
            throw new RuntimeException('Google Drive is not connected. Connect it on System health.');
        }

        if (! static::hasPassphrase()) {
            throw new RuntimeException('Off-site backups need BACKUP_PASSPHRASE in .env (at least 12 characters), to encrypt the copies.');
        }

        $folder = $this->folderId();
        $uploaded = [];

        foreach ($paths as $path) {
            $encrypted = $this->encrypt($path);

            try {
                $this->upload($encrypted, $folder);
                $uploaded[] = basename($encrypted);
            } finally {
                File::delete($encrypted);
            }
        }

        $this->removeOld($folder);

        Cache::forever(self::LAST_COPY, ['at' => now()->toIso8601String(), 'files' => $uploaded]);

        return $uploaded;
    }

    /** AES-256 with the passphrase, by openssl, beside the original. */
    protected function encrypt(string $path): string
    {
        $target = $path.'.enc';

        $result = Process::env(['SCHOOLHUB_BACKUP_PASSPHRASE' => (string) config('services.google_drive.passphrase')])
            ->timeout(1800)
            ->run(['openssl', 'enc', '-aes-256-cbc', '-pbkdf2', '-iter', '200000', '-salt', '-in', $path, '-out', $target, '-pass', 'env:SCHOOLHUB_BACKUP_PASSPHRASE']);

        if ($result->failed() || ! is_file($target)) {
            File::delete($target);

            throw new RuntimeException('Could not encrypt '.basename($path).' for Google Drive: '.trim($result->errorOutput() ?: $result->output()));
        }

        return $target;
    }

    /** The "SchoolHub backups" folder, made the first time. */
    protected function folderId(): string
    {
        $known = PlatformSetting::get(self::FOLDER_ID);

        if ($known) {
            $check = $this->send(fn () => $this->api()->get("https://www.googleapis.com/drive/v3/files/{$known}", ['fields' => 'id,trashed']));

            if ($check->successful() && ! $check->json('trashed')) {
                return $known;
            }
        }

        $created = $this->send(fn () => $this->api()->post('https://www.googleapis.com/drive/v3/files', [
            'name' => (string) config('services.google_drive.folder', 'SchoolHub backups'),
            'mimeType' => 'application/vnd.google-apps.folder',
        ]));

        $id = (string) $created->json('id');

        if (! $created->successful() || $id === '') {
            throw new RuntimeException('Could not make the backups folder in Google Drive: '.$this->reason($created));
        }

        PlatformSetting::put(self::FOLDER_ID, $id);

        return $id;
    }

    /** A resumable upload, sent in pieces so large backups get through. */
    protected function upload(string $path, string $folder): void
    {
        $size = (int) filesize($path);

        $session = $this->send(fn () => $this->api()
            ->withHeaders(['X-Upload-Content-Type' => 'application/octet-stream', 'X-Upload-Content-Length' => (string) $size])
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable', [
                'name' => basename($path),
                'parents' => [$folder],
            ]));

        $location = $session->header('Location');

        if (! $session->successful() || $location === '') {
            throw new RuntimeException('Google Drive did not accept '.basename($path).': '.$this->reason($session));
        }

        $handle = fopen($path, 'rb');

        if (! $handle) {
            throw new RuntimeException('Could not read '.basename($path).'.');
        }

        try {
            $offset = 0;

            do {
                $piece = (string) fread($handle, self::CHUNK);
                $end = $offset + strlen($piece) - 1;
                $range = $size === 0 ? 'bytes */0' : "bytes {$offset}-{$end}/{$size}";

                $response = $this->send(fn () => $this->api()
                    ->withHeaders(['Content-Range' => $range])
                    ->withBody($piece, 'application/octet-stream')
                    ->timeout(300)
                    ->put($location));

                if ($response->successful()) {
                    return;
                }

                if ($response->status() !== 308) {
                    throw new RuntimeException('Uploading '.basename($path).' to Google Drive failed: '.$this->reason($response));
                }

                $offset = $end + 1;
            } while ($offset < $size);
        } finally {
            fclose($handle);
        }

        throw new RuntimeException('Google Drive did not finish receiving '.basename($path).'.');
    }

    /** Delete copies older than the days kept. */
    protected function removeOld(string $folder): void
    {
        $cutoff = now()->subDays(max(1, (int) config('services.google_drive.keep_days', 30)));

        $list = $this->send(fn () => $this->api()->get('https://www.googleapis.com/drive/v3/files', [
            'q' => "'{$folder}' in parents and trashed = false",
            'fields' => 'files(id,name,createdTime)',
            'pageSize' => 1000,
        ]));

        foreach ((array) $list->json('files', []) as $file) {
            if (isset($file['id'], $file['createdTime']) && Carbon::parse($file['createdTime'])->lt($cutoff)) {
                $this->send(fn () => $this->api()->delete("https://www.googleapis.com/drive/v3/files/{$file['id']}"));
            }
        }
    }

    /** A short-lived access token, renewed from the refresh token. */
    protected function accessToken(): string
    {
        if ($token = Cache::get($this->tokenCacheKey())) {
            return $token;
        }

        $response = $this->send(fn () => Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google_drive.client_id'),
            'client_secret' => config('services.google_drive.client_secret'),
            'refresh_token' => PlatformSetting::get(self::REFRESH_TOKEN),
            'grant_type' => 'refresh_token',
        ]));

        $token = (string) $response->json('access_token');

        if (! $response->successful() || $token === '') {
            throw new RuntimeException($response->json('error') === 'invalid_grant'
                ? 'Google Drive access was removed or has expired. Connect Google Drive again on System health.'
                : 'Google refused SchoolHub\'s Drive access (HTTP '.$response->status().').');
        }

        Cache::put($this->tokenCacheKey(), $token, max(60, (int) $response->json('expires_in', 3600) - 120));

        return $token;
    }

    /**
     * Google's own explanation of a refusal, with what to do for the
     * common ones (Drive not allowed, Drive API switched off).
     */
    protected function reason(Response $response): string
    {
        $message = (string) ($response->json('error.message') ?: $response->json('error_description') ?: '');
        $reason = (string) ($response->json('error.errors.0.reason') ?: $response->json('error.details.0.reason') ?: '');
        $text = 'HTTP '.$response->status().($message !== '' ? ', '.$message : '');

        return match (true) {
            in_array($reason, ['insufficientPermissions', 'ACCESS_TOKEN_SCOPE_INSUFFICIENT'], true) || str_contains($message, 'insufficient') => $text.'. Drive was not allowed when connecting: click Connect Google Drive again and tick the Google Drive box on Google\'s page.',
            in_array($reason, ['accessNotConfigured', 'SERVICE_DISABLED'], true) || str_contains($message, 'has not been used') || str_contains($message, 'disabled') => $text.'. Enable the Google Drive API in the Google Cloud project (APIs & Services), wait a few minutes and try again.',
            default => $text.'.',
        };
    }

    protected function api(): PendingRequest
    {
        return Http::withToken($this->accessToken())->acceptJson()->timeout(60);
    }

    protected function tokenCacheKey(): string
    {
        return 'google-drive-token:'.md5((string) config('services.google_drive.client_id'));
    }

    /** The email address in the id_token Google returned with the tokens. */
    protected function emailFromIdToken(string $idToken): ?string
    {
        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            return null;
        }

        $claims = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true);

        return is_array($claims) && is_string($claims['email'] ?? null) ? $claims['email'] : null;
    }

    /**
     * @param  callable(): Response  $request
     */
    protected function send(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException) {
            throw new RuntimeException('Google Drive could not be reached. Check the server\'s internet connection.');
        }
    }
}
