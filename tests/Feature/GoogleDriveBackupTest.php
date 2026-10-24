<?php

use App\Filament\Pages\SystemHealth as SystemHealthPage;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Backups\GoogleDrive;
use App\Support\SystemHealth;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->owner = User::factory()->create()->assignRole('Super Admin');

    config([
        'services.google_drive.client_id' => 'client-id',
        'services.google_drive.client_secret' => 'client-secret',
        'services.google_drive.passphrase' => 'a long backup passphrase',
        'services.google_drive.keep_days' => 30,
    ]);

    $this->dir = storage_path('framework/testing/drive-backups');
    File::ensureDirectoryExists($this->dir);
});

afterEach(function () {
    File::deleteDirectory($this->dir);
});

/** Google's side: tokens, the folder, a resumable upload, the folder's files. */
function fakeGoogleDrive(): void
{
    $GLOBALS['driveUploads'] = [];

    Http::fake(function (Request $request) {
        $url = $request->url();

        return match (true) {
            str_starts_with($url, 'https://oauth2.googleapis.com/token') => Http::response([
                'access_token' => 'access-1',
                'expires_in' => 3600,
                'refresh_token' => 'refresh-1',
                'scope' => $GLOBALS['driveGranted'] ?? 'openid https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/drive.file',
                'id_token' => 'x.'.rtrim(strtr(base64_encode(json_encode(['email' => 'owner@gmail.com'])), '+/', '-_'), '=').'.y',
            ]),
            str_starts_with($url, 'https://www.googleapis.com/upload/drive/v3/files') => Http::response([], 200, ['Location' => 'https://upload.test/session-'.count($GLOBALS['driveUploads'])]),
            str_starts_with($url, 'https://upload.test/') => (function () use ($request) {
                $GLOBALS['driveUploads'][] = $request->body();

                return Http::response(['id' => 'file-'.count($GLOBALS['driveUploads'])], 200);
            })(),
            $request->method() === 'POST' && $url === 'https://www.googleapis.com/drive/v3/files' => Http::response(['id' => 'folder-1']),
            $request->method() === 'GET' && str_starts_with($url, 'https://www.googleapis.com/drive/v3/files?') => Http::response(['files' => [
                ['id' => 'old-1', 'name' => 'schoolhub-db-old.sql.gz.enc', 'createdTime' => now()->subDays(40)->toIso8601String()],
                ['id' => 'new-1', 'name' => 'schoolhub-db-new.sql.gz.enc', 'createdTime' => now()->subDays(2)->toIso8601String()],
            ]]),
            default => Http::response([], 204),
        };
    });
}

it('connects the owner\'s Google Drive through Google\'s consent page', function () {
    fakeGoogleDrive();
    $this->actingAs($this->owner);

    $redirect = $this->get(route('filament.admin.google-drive.connect'))->assertRedirect();
    parse_str((string) parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($redirect->headers->get('Location'))->toStartWith('https://accounts.google.com/o/oauth2/v2/auth')
        ->and($query['scope'])->toContain('auth/drive.file')
        ->and($query['access_type'])->toBe('offline');

    $this->get(route('filament.admin.google-drive.callback', ['state' => 'wrong', 'code' => 'abc']))->assertRedirect();
    expect(GoogleDrive::isConnected())->toBeFalse();

    $this->get(route('filament.admin.google-drive.connect'));
    $this->get(route('filament.admin.google-drive.callback', ['state' => session('google-drive.state'), 'code' => 'abc']))
        ->assertRedirect(SystemHealthPage::getUrl(panel: 'admin'));

    expect(GoogleDrive::isConnected())->toBeTrue()
        ->and(GoogleDrive::account())->toBe('owner@gmail.com')
        ->and(PlatformSetting::query()->whereKey(GoogleDrive::REFRESH_TOKEN)->toBase()->value('value'))->not->toBe('refresh-1');
});

it('lets only the platform owner connect a Drive', function () {
    $this->actingAs(User::factory()->create()->assignRole('School Admin'));

    $this->get(route('filament.admin.google-drive.connect'))->assertForbidden();
});

it('encrypts each backup before it leaves the server, and deletes copies older than the days kept', function () {
    fakeGoogleDrive();
    PlatformSetting::put(GoogleDrive::REFRESH_TOKEN, 'refresh-1');
    PlatformSetting::put(GoogleDrive::ACCOUNT, 'owner@gmail.com');

    $backup = $this->dir.'/schoolhub-db-2026-10-24-013000.sql.gz';
    File::put($backup, str_repeat('learner fees and marks ', 2000));

    $uploaded = app(GoogleDrive::class)->copy([$backup]);

    expect($uploaded)->toBe(['schoolhub-db-2026-10-24-013000.sql.gz.enc'])
        ->and($GLOBALS['driveUploads'])->toHaveCount(1)
        ->and($GLOBALS['driveUploads'][0])->not->toContain('learner fees')
        ->and(File::exists($backup.'.enc'))->toBeFalse()
        ->and(Cache::get(GoogleDrive::LAST_COPY)['files'])->toBe($uploaded);

    // The copy opens with the passphrase.
    File::put($this->dir.'/copy.enc', $GLOBALS['driveUploads'][0]);
    $opened = Process::env(['P' => 'a long backup passphrase'])->run(['openssl', 'enc', '-d', '-aes-256-cbc', '-pbkdf2', '-iter', '200000', '-in', $this->dir.'/copy.enc', '-pass', 'env:P']);
    expect($opened->output())->toBe(File::get($backup));

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/files/old-1'));
    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/files/new-1'));
});

it('refuses to send unencrypted copies when there is no passphrase', function () {
    fakeGoogleDrive();
    config(['services.google_drive.passphrase' => null]);
    PlatformSetting::put(GoogleDrive::REFRESH_TOKEN, 'refresh-1');

    File::put($this->dir.'/schoolhub-db.sql.gz', 'data');

    expect(fn () => app(GoogleDrive::class)->copy([$this->dir.'/schoolhub-db.sql.gz']))->toThrow(RuntimeException::class, 'BACKUP_PASSPHRASE');
    Http::assertNothingSent();
});

it('shows the off-site copy on System health, and lets the owner disconnect', function () {
    fakeGoogleDrive();
    $row = fn () => collect(app(SystemHealth::class)->checks())->firstWhere('label', 'Off-site copy (Google Drive)');

    expect($row()['status'])->toBe('warning')
        ->and($row()['summary'])->toContain('not connected');

    PlatformSetting::put(GoogleDrive::REFRESH_TOKEN, 'refresh-1');
    PlatformSetting::put(GoogleDrive::ACCOUNT, 'owner@gmail.com');
    Cache::forever(GoogleDrive::LAST_COPY, ['at' => now()->subHours(3)->toIso8601String(), 'files' => ['a.enc']]);

    expect($row()['status'])->toBe('ok')
        ->and($row()['summary'])->toContain('owner@gmail.com');

    $this->actingAs($this->owner);
    Livewire::test(SystemHealthPage::class)
        ->assertActionVisible('disconnectGoogleDrive')
        ->callAction('disconnectGoogleDrive');

    expect(GoogleDrive::isConnected())->toBeFalse();
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://oauth2.googleapis.com/revoke'));
});

it('refuses to connect when the Drive box was left unticked on Google\'s page', function () {
    $GLOBALS['driveGranted'] = 'openid https://www.googleapis.com/auth/userinfo.email';
    fakeGoogleDrive();
    $this->actingAs($this->owner);

    $this->get(route('filament.admin.google-drive.connect'));
    $this->get(route('filament.admin.google-drive.callback', ['state' => session('google-drive.state'), 'code' => 'abc']));

    expect(GoogleDrive::isConnected())->toBeFalse();
    unset($GLOBALS['driveGranted']);
});

it('explains a refused upload with what to do', function () {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'access-1', 'expires_in' => 3600]),
        'www.googleapis.com/*' => Http::response(['error' => [
            'code' => 403,
            'message' => 'Request had insufficient authentication scopes.',
            'errors' => [['reason' => 'insufficientPermissions']],
        ]], 403),
    ]);
    PlatformSetting::put(GoogleDrive::REFRESH_TOKEN, 'refresh-1');
    File::put($this->dir.'/schoolhub-db.sql.gz', 'data');

    expect(fn () => app(GoogleDrive::class)->copy([$this->dir.'/schoolhub-db.sql.gz']))
        ->toThrow(RuntimeException::class, 'tick the Google Drive box');
});
