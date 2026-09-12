<?php

namespace App\Filament\App\Resources\FeeStructures\Pages;

use App\Filament\App\Resources\FeeStructures\FeeStructureResource;
use App\Models\FeeStructure;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateFeeStructure extends CreateRecord
{
    protected static string $resource = FeeStructureResource::class;

    protected array $classIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // class_ids is dehydrated(false), so it is NOT in $data — read it
        // from the live form state instead.
        $this->classIds = (array) ($this->form->getRawState()['class_ids'] ?? []);

        // Non-recurring fees have no meaningful term/year — set safe defaults
        // so the unique key stays valid.
        if (($data['frequency'] ?? 'per_term') !== 'per_term') {
            $data['term'] = $data['term'] ?? 'N/A';
            $data['academic_year'] = $data['academic_year'] ?? (string) now()->year;
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $classIds = $this->classIds;

        if (empty($classIds)) {
            throw new \RuntimeException('Please select at least one class.');
        }

        $first = null;
        $created = 0;
        $skipped = 0;

        foreach ($classIds as $classId) {
            $payload = array_merge($data, ['school_class_id' => $classId]);

            $exists = FeeStructure::where('school_id', $payload['school_id'])
                ->where('school_class_id', $classId)
                ->where('name', $payload['name'])
                ->where('term', $payload['term'])
                ->where('academic_year', $payload['academic_year'])
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $record = FeeStructure::create($payload);
            $created++;
            $first ??= $record;
        }

        Notification::make()
            ->title("Created {$created} fee record(s)" . ($skipped ? ", skipped {$skipped} already existing" : ''))
            ->success()
            ->send();

        return $first ?? FeeStructure::where('school_id', $data['school_id'])
            ->where('name', $data['name'])
            ->firstOrFail();
    }
}