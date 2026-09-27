<?php

namespace App\Filament\Imports;

use App\Models\Guardian;
use App\Models\Student;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Validation\Rule;

class StudentImporter extends Importer
{
    protected static ?string $model = Student::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'max:255'])
                ->example('ARINDA MOREEN'),

            ImportColumn::make('admission_no')
                ->label('Adm. No.')
                ->requiredMapping()
                ->rules(['required', 'max:50'])
                ->example('001'),

            ImportColumn::make('schoolClass')
                ->label('Class')
                ->relationship(resolveUsing: 'name')
                ->requiredMapping()
                ->example('S1'),

            ImportColumn::make('section')
                ->relationship(resolveUsing: 'name')
                ->example('A'),

            ImportColumn::make('gender')
                ->rules(['nullable', Rule::in(array_keys(Student::GENDERS))])
                ->example('female'),

            ImportColumn::make('guardian_name')
                ->label('Guardian name')
                ->example('Ampire Provia'),

            ImportColumn::make('guardian_phone')
                ->label('Guardian phone')
                ->example('0775449733'),

            ImportColumn::make('status')
                ->rules(['nullable', Rule::in(array_keys(Student::STATUSES))])
                ->default('active')
                ->example('active'),

            ImportColumn::make('date_of_birth')
                ->label('D.O.B.')
                ->rules(['nullable', 'date'])
                ->example('1996-04-12'),

            ImportColumn::make('admission_date')
                ->rules(['nullable', 'date'])
                ->example('2026-09-08'),
        ];
    }

    public function resolveRecord(): ?Student
    {
        return Student::firstOrNew([
            'admission_no' => $this->data['admission_no'],
        ]);
    }

    protected function afterSave(): void
    {
        $phone = $this->data['guardian_phone'] ?? null;

        if (filled($phone)) {
            $guardian = Guardian::firstOrCreate(
                ['phone' => $phone],
                ['name' => $this->data['guardian_name'] ?? 'Unknown'],
            );

            if (! $this->record->guardian_id) {
                $this->record->guardian_id = $guardian->id;
                $this->record->saveQuietly();
            }
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = number_format($import->successful_rows).' student(s) imported successfully.';

        if ($failedCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedCount).' row(s) failed to import.';
        }

        return $body;
    }
}
