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

        // The school picker is only shown to a Super Admin.
        $data['school_id'] ??= auth()->user()->school_id;

        // Only recurring fees belong to a term. Admission and one-off
        // charges are not term-specific, so term_id stays null.
        if (($data['frequency'] ?? 'per_term') !== 'per_term') {
            $data['term_id'] = null;
        }

        return $data;
    }

    /**
     * handleRecordCreation() sends its own "Created N fee records"
     * message, which says more than the standard one.
     */
    protected function getCreatedNotification(): ?Notification
    {
        return null;
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

            // A fee is a duplicate when the same name already exists for the
            // same class, term AND residency. Residency matters: "Tuition"
            // for day students and "Tuition" for boarders are two different
            // fees, not a duplicate.
            $exists = FeeStructure::where('school_id', $payload['school_id'])
                ->where('school_class_id', $classId)
                ->where('name', $payload['name'])
                ->where('term_id', $payload['term_id'] ?? null)
                ->where('residency_type_id', $payload['residency_type_id'] ?? null)
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