<?php

namespace App\Filament\App\Resources\Students\Pages;

use App\Filament\App\Resources\Students\StudentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * A learner's record, read only: what teachers (and anyone without
 * Admissions) see. The admissions office edits from here.
 */
class ViewStudent extends ViewRecord
{
    protected static string $resource = StudentResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return StudentResource::canEdit($this->getRecord()) ? null : 'Only the admissions office can change these details.';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->visible(fn (): bool => StudentResource::canEdit($this->getRecord())),
        ];
    }
}
