<?php

namespace App\Filament\App\Resources\Users\Pages;

use App\Filament\App\Resources\Users\Pages\Concerns\SyncsUserAccess;
use App\Filament\App\Resources\Users\UserResource;
use App\Filament\Support\ConfirmWithPassword;
use App\Filament\Support\Pages\EditRecordPage;
use Filament\Actions\DeleteAction;

class EditUser extends EditRecordPage
{
    use SyncsUserAccess;

    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data + $this->accessFormState($this->record);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->prepareAccess($data);
    }

    protected function afterSave(): void
    {
        $this->syncStaffAndTeaching($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            ConfirmWithPassword::on(DeleteAction::make()
                ->hidden(fn () => $this->record->is(auth()->user()))), // no deleting yourself
        ];
    }
}
