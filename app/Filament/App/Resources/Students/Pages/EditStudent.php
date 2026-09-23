<?php

namespace App\Filament\App\Resources\Students\Pages;

use App\Filament\App\Resources\Students\StudentResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('admissionLetter')
                ->label('Admission letter')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(fn () => route('filament.app.students.admission-letter', $this->record))
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }
}
