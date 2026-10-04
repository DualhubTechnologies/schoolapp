<?php

namespace App\Filament\App\Resources\Students\Pages;

use App\Filament\App\Resources\Students\StudentResource;
use App\Filament\Support\ConfirmWithPassword;
use App\Filament\Support\Pages\EditRecordPage;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Illuminate\Contracts\Support\Htmlable;

class EditStudent extends EditRecordPage
{
    protected static string $resource = StudentResource::class;

    /**
     * How complete the learner's details are, and what is still missing,
     * since quick admission only asks for the essentials.
     */
    public function getSubheading(): string|Htmlable|null
    {
        $student = $this->getRecord();

        if (! $student instanceof Student) {
            return parent::getSubheading();
        }

        $missing = array_keys(array_filter($student->profileChecklist(), fn (bool $done): bool => ! $done));

        if ($missing === []) {
            return 'Profile complete. Make your changes, then press Save.';
        }

        return "Profile {$student->profilePercent()}% complete — still to add: ".implode(', ', $missing).'.';
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
