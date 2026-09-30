<?php

namespace App\Filament\App\Resources\Staff\Pages;

use App\Filament\App\Resources\Staff\StaffResource;
use App\Services\StaffCsvImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ListStaff extends ListRecords
{
    use WithFileUploads;

    protected static string $resource = StaffResource::class;

    /** The CSV being uploaded. */
    public ?TemporaryUploadedFile $csvFile = null;

    /** Where the checked file waits for "Import" (local disk). */
    public ?string $storedFile = null;

    /** upload -> preview -> complete */
    public string $importStep = 'upload';

    /** @var array<string, mixed> */
    public array $validationResult = [];

    /** @var array<string, mixed> */
    public array $importResult = [];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadTemplate')
                ->label('CSV template')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => response()->download(
                    StaffCsvImporter::generateTemplate(auth()->user()?->school_id),
                    'staff-import-template.csv',
                )),

            Action::make('importStaff')
                ->label('Import staff')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalHeading('Import staff')
                ->modalWidth('5xl')
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->mountUsing(fn () => $this->resetImport())
                ->modalContent(fn (): View => view('filament.resources.staff.pages.import-modal', [
                    'importStep' => $this->importStep,
                    'csvFile' => $this->csvFile,
                    'validationResult' => $this->validationResult,
                    'importResult' => $this->importResult,
                ])),

            CreateAction::make(),
        ];
    }

    public function uploadAndValidate(): void
    {
        $this->validate([
            'csvFile' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $stored = $this->csvFile?->store('imports', 'local');

        if (! is_string($stored)) {
            $this->addError('csvFile', 'The file could not be saved. Please try again.');

            return;
        }

        $this->storedFile = $stored;
        $this->validationResult = (new StaffCsvImporter((int) auth()->user()->school_id))->validate($this->storedFile);
        $this->importStep = 'preview';
    }

    public function startImport(): void
    {
        if (! $this->storedFile || ! Storage::disk('local')->exists($this->storedFile)) {
            Notification::make()->title('Upload the file again')->body('The checked file is no longer available.')->danger()->send();
            $this->resetImport();

            return;
        }

        $this->importResult = (new StaffCsvImporter((int) auth()->user()->school_id))->import($this->storedFile);
        $this->storedFile = null;
        $this->importStep = 'complete';

        Notification::make()
            ->title("{$this->importResult['imported']} staff imported")
            ->body($this->importResult['skipped'] ? "{$this->importResult['skipped']} rows skipped." : null)
            ->success()
            ->send();
    }

    public function resetImport(): void
    {
        if ($this->storedFile) {
            Storage::disk('local')->delete($this->storedFile);
        }

        $this->csvFile = null;
        $this->storedFile = null;
        $this->importStep = 'upload';
        $this->validationResult = [];
        $this->importResult = [];
    }
}
