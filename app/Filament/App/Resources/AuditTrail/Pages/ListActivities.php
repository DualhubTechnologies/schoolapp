<?php

namespace App\Filament\App\Resources\AuditTrail\Pages;

use App\Filament\App\Resources\AuditTrail\AuditTrailResource;
use Filament\Resources\Pages\ListRecords;

class ListActivities extends ListRecords
{
    protected static string $resource = AuditTrailResource::class;

    // No header actions — the audit trail is read-only.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
