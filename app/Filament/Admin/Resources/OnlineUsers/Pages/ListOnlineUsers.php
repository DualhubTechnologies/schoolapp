<?php

namespace App\Filament\Admin\Resources\OnlineUsers\Pages;

use App\Filament\Admin\Resources\OnlineUsers\OnlineUserResource;
use App\Models\UserSession;
use Filament\Resources\Pages\ListRecords;

class ListOnlineUsers extends ListRecords
{
    protected static string $resource = OnlineUserResource::class;

    public function getSubheading(): string
    {
        return 'Everyone signed in right now. "Active now" means they used SchoolHub in the last '
            .UserSession::ACTIVE_MINUTES.' minutes. The list refreshes every 30 seconds.';
    }

    /**
     * No header actions — this is a live view, not something to edit.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
