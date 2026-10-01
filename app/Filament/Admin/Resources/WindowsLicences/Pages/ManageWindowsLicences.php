<?php

namespace App\Filament\Admin\Resources\WindowsLicences\Pages;

use App\Filament\Admin\Resources\WindowsLicences\WindowsLicenceResource;
use Filament\Resources\Pages\ManageRecords;

class ManageWindowsLicences extends ManageRecords
{
    protected static string $resource = WindowsLicenceResource::class;

    protected ?string $subheading = 'Keys for schools using SchoolHub on Windows. A school sends its name and code from its Licence page; issue its key here.';

    protected function getHeaderActions(): array
    {
        return [
            WindowsLicenceResource::issueAction(),
        ];
    }
}
