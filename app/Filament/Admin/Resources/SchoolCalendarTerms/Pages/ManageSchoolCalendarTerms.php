<?php

namespace App\Filament\Admin\Resources\SchoolCalendarTerms\Pages;

use App\Filament\Admin\Resources\SchoolCalendarTerms\SchoolCalendarTermResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSchoolCalendarTerms extends ManageRecords
{
    protected static string $resource = SchoolCalendarTermResource::class;

    protected ?string $subheading = 'A paid term runs to the day before the next term opens, so schools pay when a term starts. Add next year\'s terms when the Ministry publishes them.';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add a term')];
    }
}
