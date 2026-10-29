<?php

namespace App\Livewire;

use App\Services\Sync\SyncClient;
use App\Support\Edition;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * A Windows app just set up from its online account: the person is
 * already signed in and working while the first sync brings the school's
 * records. A banner at the top of every page says so and when everything
 * is here (or that it waits for the internet). The sync itself runs in the
 * background (SyncClient::requestSync), never in the page.
 */
class FirstSyncBanner extends Component
{
    /** running, waiting (no internet) or done. */
    public string $state = 'running';

    public static function pending(): bool
    {
        return Edition::isDesktop() && DB::table('app_state')->where('key', SyncClient::FIRST)->exists();
    }

    /** On the first page: asks for the sync, unless one is already on its way. */
    public function start(SyncClient $client): void
    {
        if ($client->status()['state'] !== 'syncing') {
            $client->requestSync();
        }

        $this->check($client);
    }

    public function check(SyncClient $client): void
    {
        if (! static::pending()) {
            $this->state = 'done';

            return;
        }

        $this->state = $client->status()['state'] === 'offline' ? 'waiting' : 'running';
    }

    public function render(): View
    {
        return view('livewire.first-sync-banner');
    }
}
