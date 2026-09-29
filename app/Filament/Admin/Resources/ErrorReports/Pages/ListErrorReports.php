<?php

namespace App\Filament\Admin\Resources\ErrorReports\Pages;

use App\Filament\Admin\Resources\ErrorReports\ErrorReportResource;
use Filament\Resources\Pages\ListRecords;

class ListErrorReports extends ListRecords
{
    protected static string $resource = ErrorReportResource::class;

    protected ?string $subheading = 'Unexpected errors from every school, one row per error. Search by the reference a user quotes, e.g. E-7K3Q9P.';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
