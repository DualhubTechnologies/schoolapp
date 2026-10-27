<?php

namespace App\Livewire;

use App\Services\Sync\SyncClient;
use App\Support\Edition;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use RuntimeException;

/**
 * A Windows app just set up from its online account: the person is
 * already signed in and working while the first sync brings the school's
 * records. A banner at the top of every page says so, runs that sync and
 * says when everything is here (or that it waits for the internet).
 */
class FirstSyncBanner extends Component
{
    /** running, waiting (no internet or another sync running) or done. */
    public string $state = 'running';

    public static function pending(): bool
    {
        return Edition::isDesktop() && DB::table('app_state')->where('key', SyncClient::FIRST)->exists();
    }

    public function runSync(SyncClient $client): void
    {
        if (! static::pending()) {
            $this->state = 'done';

            return;
        }

        try {
            $client->run();
        } catch (LockTimeoutException) {
            $this->state = 'waiting'; // another page is running it; check again shortly

            return;
        } catch (ConnectionException|RuntimeException) {
            $this->state = 'waiting';

            return;
        }

        DB::table('app_state')->where('key', SyncClient::FIRST)->delete();
        $this->state = 'done';
    }

    public function render(): View
    {
        return view('livewire.first-sync-banner');
    }
}
