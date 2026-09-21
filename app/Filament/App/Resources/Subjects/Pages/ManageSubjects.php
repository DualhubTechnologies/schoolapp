<?php

namespace App\Filament\App\Resources\Subjects\Pages;

use App\Filament\App\Resources\Subjects\SubjectResource;
use App\Models\School;
use App\Services\Academics\CurriculumSetup;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageSubjects extends ManageRecords
{
    protected static string $resource = SubjectResource::class;

    public function getSubheading(): ?string
    {
        return 'Subjects per curriculum. Assign them to classes, and set who teaches each, under Academics → Classes.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('setup')
                ->label('Set up Uganda curriculum')
                ->icon('heroicon-o-sparkles')
                ->requiresConfirmation()
                ->modalHeading('Set up the Uganda curriculum')
                ->modalDescription('Adds the standard subjects for the curricula your classes follow (primary, new lower-secondary, A-Level), assigns them to classes (compulsory or elective by class), and creates the grading scales and A-Level combinations. Anything you already have is left as it is.')
                ->modalSubmitActionLabel('Set up')
                ->action(function (CurriculumSetup $setup) {
                    $result = $setup->run(School::findOrFail(auth()->user()->school_id));

                    Notification::make()
                        ->title('Curriculum set up')
                        ->body("{$result['subjects']} subjects, {$result['class_subjects']} class assignments, {$result['scales']} grading scales and {$result['combinations']} combinations added.")
                        ->success()
                        ->send();
                }),

            CreateAction::make()
                ->label('New subject')
                ->mutateDataUsing(fn (array $data) => $data + ['school_id' => auth()->user()->school_id]),
        ];
    }
}
