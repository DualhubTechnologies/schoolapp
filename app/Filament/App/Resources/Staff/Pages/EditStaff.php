<?php

namespace App\Filament\App\Resources\Staff\Pages;

use App\Filament\App\Resources\Staff\StaffResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStaff extends EditRecord
{
    protected static string $resource = StaffResource::class;

    public function getSubheading(): ?string
    {
        return 'Pay is set in the tabs below the form: Salary, Allowances, Deductions, Bank details and Arrears.';
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
