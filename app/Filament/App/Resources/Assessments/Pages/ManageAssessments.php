<?php

namespace App\Filament\App\Resources\Assessments\Pages;

use App\Filament\App\Resources\Assessments\AssessmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAssessments extends ManageRecords
{
    protected static string $resource = AssessmentResource::class;

    public function getSubheading(): ?string
    {
        return 'Each exam\'s weight is its share of the term result. O-Level (new curriculum): continuous assessment 20% + end of term 80%.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New exam')
                ->mutateDataUsing(fn (array $data) => $data + ['school_id' => auth()->user()->school_id, 'status' => 'open']),
        ];
    }
}
