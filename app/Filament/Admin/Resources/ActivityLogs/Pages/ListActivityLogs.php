<?php

namespace App\Filament\Admin\Resources\ActivityLogs\Pages;

use App\Filament\Admin\Resources\ActivityLogs\ActivityLogResource;
use Filament\Resources\Pages\ListRecords;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    protected ?string $subheading = 'Sign-ins, failed sign-ins and record changes from every school, newest first.';

    /**
     * No header actions — the log is read-only.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
