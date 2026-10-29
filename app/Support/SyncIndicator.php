<?php

namespace App\Support;

use App\Filament\Pages\OnlineSync;
use App\Services\Sync\SyncClient;

/**
 * The Windows app's online/offline dot in the top bar: whether it is in
 * step with the school's online account. The page also checks the
 * computer's own connection (it turns grey the moment the internet goes)
 * and asks again every so often (SyncStatusController).
 */
class SyncIndicator
{
    /** @return array{tone: string, label: string, title: string, url: string|null, syncing: bool} */
    public static function now(): array
    {
        $status = app(SyncClient::class)->status();
        $last = $status['last'] ? 'Last synced '.$status['last']->diffForHumans().'.' : 'Not synced yet.';

        [$tone, $label, $title] = match ($status['state']) {
            'syncing' => ['blue', 'Syncing', 'Syncing with online SchoolHub in the background. Keep working.'],
            'offline' => ['grey', 'Offline', "No internet: everything is saved on this computer and syncs when it is back online. {$last}"],
            'error' => ['red', 'Sync problem', 'The last sync did not finish: '.$status['error']],
            'review' => ['amber', $status['reviews'].' to check', "Synced, but {$status['reviews']} possible ".str('duplicate')->plural($status['reviews']).' need a decision. '.$last],
            'ok' => ['green', 'Online', "In step with online SchoolHub. {$last}"],
            default => ['grey', 'Not syncing', 'This computer works on its own. Link it to the school\'s online account to sync.'],
        };

        return [
            'tone' => $tone,
            'label' => $label,
            'title' => $title,
            'url' => OnlineSync::canAccess() ? OnlineSync::getUrl() : null,
            'syncing' => $status['state'] === 'syncing',
        ];
    }
}
