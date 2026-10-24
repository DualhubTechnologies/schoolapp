<?php

namespace App\Http\Controllers;

use App\Filament\Pages\SystemHealth;
use App\Services\Backups\GoogleDrive;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Connecting the platform owner's Google Drive for off-site backups
 * (GoogleDrive): off to Google to allow it, and back again. Started from
 * System health; Super Admin only.
 */
class GoogleDriveController extends Controller
{
    protected const STATE = 'google-drive.state';

    public function connect(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('Super Admin'), 403);

        if (! GoogleDrive::isConfigured()) {
            return $this->back('Google Drive is not set up', 'Add GOOGLE_DRIVE_CLIENT_ID and GOOGLE_DRIVE_CLIENT_SECRET to .env first.', 'danger');
        }

        $state = Str::random(40);
        $request->session()->put(self::STATE, $state);

        return redirect()->away(app(GoogleDrive::class)->authorizationUrl($state));
    }

    public function callback(Request $request, GoogleDrive $drive): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('Super Admin'), 403);

        $expected = $request->session()->pull(self::STATE);

        if (! is_string($expected) || ! hash_equals($expected, (string) $request->query('state'))) {
            return $this->back('Google Drive not connected', 'The request had expired. Click Connect Google Drive again.', 'danger');
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return $this->back('Google Drive not connected', 'Access was not allowed in Google.', 'warning');
        }

        try {
            $account = $drive->connect((string) $request->query('code'));
        } catch (RuntimeException $e) {
            return $this->back('Google Drive not connected', $e->getMessage(), 'danger');
        }

        return $this->back('Google Drive connected', "Each night's backup will be encrypted and copied to {$account}.", 'success');
    }

    protected function back(string $title, string $body, string $status): RedirectResponse
    {
        Notification::make()->title($title)->body($body)->status($status)->send();

        return redirect(SystemHealth::getUrl(panel: 'admin'));
    }
}
