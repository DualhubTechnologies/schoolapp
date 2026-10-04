<?php

namespace App\Filament\Admin\Resources\ErrorReports\Pages;

use App\Filament\Admin\Resources\ErrorReports\ErrorReportResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewErrorReport extends ViewRecord
{
    protected static string $resource = ErrorReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ErrorReportResource::copyAction(),
            ErrorReportResource::resolveAction(),
            DeleteAction::make(),
        ];
    }
}
