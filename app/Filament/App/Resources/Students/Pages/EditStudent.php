<?php

namespace App\Filament\App\Resources\Students\Pages;

use App\Filament\App\Resources\Students\StudentResource;
use App\Filament\Support\ConfirmWithPassword;
use App\Filament\Support\Pages\EditRecordPage;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Illuminate\Contracts\Support\Htmlable;

class EditStudent extends EditRecordPage
{
    protected static string $resource = StudentResource::class;

    /** How complete the profile is shows in the summary card at the top. */
    public function getSubheading(): string|Htmlable|null
    {
        return 'Change any detail below, then press Save.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('admissionLetter')
                ->label('Admission letter')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(fn () => route('filament.app.students.admission-letter', $this->record))
                ->openUrlInNewTab(),
            ConfirmWithPassword::on(DeleteAction::make()),
        ];
    }
}
