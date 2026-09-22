<?php

namespace App\Filament\App\Resources\Users\Pages;

use App\Filament\App\Resources\Users\Pages\Concerns\SyncsUserAccess;
use App\Filament\App\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
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
            DeleteAction::make()
                ->hidden(fn () => $this->record->is(auth()->user())), // no deleting yourself
        ];
    }
}
