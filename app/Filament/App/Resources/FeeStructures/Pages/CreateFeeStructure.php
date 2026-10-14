<?php

namespace App\Filament\App\Resources\FeeStructures\Pages;

use App\Filament\App\Resources\FeeStructures\FeeStructureResource;
use App\Filament\App\Resources\FeeStructures\Schemas\FeeStructureForm;
use App\Filament\Support\Pages\CreateRecordPage;
use App\Models\FeeStructure;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class CreateFeeStructure extends CreateRecordPage
{
    protected static string $resource = FeeStructureResource::class;

    protected static ?string $title = 'Add a fee';

    /** Fees are usually added several in a row: tuition, lunch, medical... */
    protected static bool $canCreateAnother = true;

    protected array $classIds = [];

    public function getSubheading(): string
    {
        return 'Pick the fee, tick the classes that pay it, choose when it is charged, then the amount.';
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()->label('Save')->icon('heroicon-m-check'),
            $this->getCreateAnotherFormAction()->label('Save & add another'),
            $this->getCancelFormAction(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // The classes and who pays what are not columns (dehydrated(false)),
        // so they are read from the live form state.
        $raw = $this->data ?? [];
        $this->classIds = array_values(array_filter((array) ($raw['class_ids'] ?? [])));
        $amountFor = $raw['amount_for'] ?? 'all';

        $data['per_residency'] = $amountFor === 'each';
        $data['residency_type_id'] = FeeStructureForm::onlyResidency($amountFor);
        $data['is_active'] ??= true;

        // The school picker is only shown to a Super Admin.
        $data['school_id'] ??= auth()->user()->school_id;

        // Only recurring fees belong to a term. Admission and one-off
        // charges are not term-specific, so term_id stays null.
        if (($data['frequency'] ?? 'per_term') !== 'per_term') {
            $data['term_id'] = null;
        }

        return FeeStructure::withWhoPays($data);
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

        // One amount for everyone (or one residency), or one per residency
        // ("Different amounts for Day and Boarding"): a fee for each.
        $variants = ($data['per_residency'] ?? false)
            ? collect((array) ($data['residency_amounts'] ?? []))
                ->filter(fn ($amount): bool => is_numeric($amount))
                ->map(fn ($amount, $residencyId): array => ['residency_type_id' => (int) $residencyId, 'amount' => $amount])
                ->values()
                ->all()
            : [[]];

        unset($data['per_residency'], $data['residency_amounts']);

        if ($variants === []) {
            Notification::make()->title('Type the amount for at least one residency')->danger()->send();

            $this->halt();
        }

        $first = null;
        $created = 0;
        $skipped = 0;

        foreach ($classIds as $classId) {
            foreach ($variants as $variant) {
                $payload = array_merge($data, $variant, ['school_class_id' => $classId]);

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
        }

        Notification::make()
            ->title($created === 1 ? "{$data['name']} added" : "{$data['name']} added: {$created} fees")
            ->body($skipped ? "{$skipped} already set, so left as they were." : null)
            ->success()
            ->send();

        return $first ?? FeeStructure::where('school_id', $data['school_id'])
            ->where('name', $data['name'])
            ->firstOrFail();
    }
}
