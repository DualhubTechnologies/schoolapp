<?php

/*
 * Syncing the Windows app with the school's online account
 * (App\Services\Sync).
 */
return [

    // The online SchoolHub the Windows app syncs with.
    'url' => env('SYNC_URL', 'https://www.schoolhubug.com'),

    // How often a linked Windows app syncs on its own while online.
    'every_minutes' => 5,

];
