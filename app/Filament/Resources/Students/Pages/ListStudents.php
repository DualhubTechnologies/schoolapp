<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use App\Jobs\ProcessStudentImport;
use App\Models\StudentImport;
use App\Services\StudentCsvImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;
use Livewire\WithFileUploads;

class ListStudents extends ListRecords
{
    use WithFileUploads;

    protected static string $resource = StudentResource::class;

    /*
    |--------------------------------------------------------------------------
    | Import state
    |--------------------------------------------------------------------------
    */

    public $csvFile = null;

    public ?int $importId = null;

    public string $importStep = 'upload';

    public bool $runInBackground = true;

    public array $validationResult = [];

    public array $importProgress = [
        'status' => 'pending',
        'total_rows' => 0,
        'processed_rows' => 0,
        'successful_rows' => 0,
        'failed_rows' => 0,
        'duplicate_rows' => 0,
        'progress_percent' => 0,
        'elapsed_seconds' => 0,
        'estimated_remaining' => null,
        'log' => [],
        'errors' => [],
    ];

    /*
    |--------------------------------------------------------------------------
    | Header actions
    |--------------------------------------------------------------------------
    */

    protected function getHeaderActions(): array
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | Download CSV template
            |--------------------------------------------------------------------------
            */

            Action::make('downloadTemplate')
                ->label('CSV template')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $path = StudentCsvImporter::generateTemplate();

                    return response()->download(
                        $path,
                        'student-import-template.csv'
                    );
                }),

            /*
            |--------------------------------------------------------------------------
            | Import students
            |--------------------------------------------------------------------------
            */

            Action::make('importStudents')
                ->label('Import students')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalHeading('Import Students')
                ->modalWidth('5xl')
                ->modalSubmitAction(false)
                ->modalCancelAction(false)

                /*
                |--------------------------------------------------------------------------
                | Reset import whenever modal opens
                |--------------------------------------------------------------------------
                */

                ->mountUsing(function () {
                    $this->resetImport();
                })

                /*
                |--------------------------------------------------------------------------
                | IMPORTANT:
                | Explicitly pass Livewire properties to the Blade view.
                |--------------------------------------------------------------------------
                */

                ->modalContent(function (): View {
                    return view(
                        'filament.resources.students.pages.import-modal',
                        [
                            'importStep' => $this->importStep,
                            'csvFile' => $this->csvFile,
                            'importId' => $this->importId,
                            'runInBackground' => $this->runInBackground,
                            'validationResult' => $this->validationResult,
                            'importProgress' => $this->importProgress,
                        ]
                    );
                }),

            /*
            |--------------------------------------------------------------------------
            | Create student
            |--------------------------------------------------------------------------
            */

            CreateAction::make()
                ->label('New student'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Upload & Validate
    |--------------------------------------------------------------------------
    */

    public function uploadAndValidate(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Validate uploaded file
        |--------------------------------------------------------------------------
        */

        $this->validate([
            'csvFile' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:10240',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Show validating state
        |--------------------------------------------------------------------------
        */

        $this->importStep = 'validating';

        /*
        |--------------------------------------------------------------------------
        | Store uploaded file
        |--------------------------------------------------------------------------
        */

        $path = $this->csvFile->store(
            'imports',
            'local'
        );

        $fileName = $this->csvFile->getClientOriginalName();

        /*
        |--------------------------------------------------------------------------
        | Create import record
        |--------------------------------------------------------------------------
        */

        $import = StudentImport::create([
            'school_id' => auth()->user()->school_id,
            'imported_by' => auth()->id(),
            'file_name' => $fileName,
            'file_path' => $path,
            'status' => 'pending',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Remember import ID
        |--------------------------------------------------------------------------
        */

        $this->importId = $import->id;

        /*
        |--------------------------------------------------------------------------
        | Validate CSV
        |--------------------------------------------------------------------------
        */

        $importer = new StudentCsvImporter($import);

        $this->validationResult = $importer->validate();

        /*
        |--------------------------------------------------------------------------
        | Move to preview
        |--------------------------------------------------------------------------
        */

        $this->importStep = 'preview';
    }

    /*
    |--------------------------------------------------------------------------
    | Start Import
    |--------------------------------------------------------------------------
    */

    public function startImport(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Make sure we have an import
        |--------------------------------------------------------------------------
        */

        if (! $this->importId) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Find import
        |--------------------------------------------------------------------------
        */

        $import = StudentImport::find($this->importId);

        /*
        |--------------------------------------------------------------------------
        | Make sure import exists and was validated
        |--------------------------------------------------------------------------
        */

        if (! $import || $import->status !== 'validated') {
            Notification::make()
                ->title('Import not ready')
                ->body('Please validate the file first.')
                ->danger()
                ->send();

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Move to importing state
        |--------------------------------------------------------------------------
        */

        $this->importStep = 'importing';

        /*
        |--------------------------------------------------------------------------
        | Background import
        |--------------------------------------------------------------------------
        */

        if ($this->runInBackground) {

            ProcessStudentImport::dispatch($import);

            $this->importProgress['status'] = 'importing';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Run immediately
            |--------------------------------------------------------------------------
            */

            ProcessStudentImport::dispatchSync($import);

            $this->refreshProgress();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Refresh import progress
    |--------------------------------------------------------------------------
    */

    public function refreshProgress(): void
    {
        if (! $this->importId) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Reload import
        |--------------------------------------------------------------------------
        */

        $import = StudentImport::find($this->importId);

        if (! $import) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Update progress
        |--------------------------------------------------------------------------
        */

        $this->importProgress = [
            'status' => $import->status,

            'total_rows' => $import->total_rows,

            'processed_rows' => $import->processed_rows,

            'successful_rows' => $import->successful_rows,

            'failed_rows' => $import->failed_rows,

            'duplicate_rows' => $import->duplicate_rows,

            'progress_percent' => $import->progress_percent,

            'elapsed_seconds' => $import->elapsed_seconds ?? 0,

            'estimated_remaining' => $import->estimated_remaining,

            'log' => $import->import_log ?? [],

            'errors' => $import->validation_errors ?? [],
        ];

        /*
        |--------------------------------------------------------------------------
        | If finished, show complete screen
        |--------------------------------------------------------------------------
        */

        if ($import->isComplete()) {
            $this->importStep = 'complete';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Reset Import
    |--------------------------------------------------------------------------
    */

    public function resetImport(): void
    {
        $this->csvFile = null;

        $this->importId = null;

        $this->importStep = 'upload';

        $this->runInBackground = true;

        $this->validationResult = [];

        $this->importProgress = [
            'status' => 'pending',
            'total_rows' => 0,
            'processed_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
            'duplicate_rows' => 0,
            'progress_percent' => 0,
            'elapsed_seconds' => 0,
            'estimated_remaining' => null,
            'log' => [],
            'errors' => [],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Format Duration
    |--------------------------------------------------------------------------
    */

    public function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }

        $minutes = floor($seconds / 60);

        $secs = $seconds % 60;

        return "{$minutes}m {$secs}s";
    }
}