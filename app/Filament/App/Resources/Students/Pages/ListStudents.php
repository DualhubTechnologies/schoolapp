<?php

namespace App\Filament\App\Resources\Students\Pages;

use App\Filament\App\Resources\Students\Schemas\StudentForm;
use App\Filament\App\Resources\Students\StudentResource;
use App\Jobs\ProcessStudentImport;
use App\Models\Student;
use App\Models\StudentImport;
use App\Services\StudentCsvImporter;
use App\Services\Subscriptions\SubscriptionManager;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Wizard\Step;
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

    /**
     * When ticked, the uploaded students are the school's existing/continuing
     * body and are imported as CONFIRMED. Left unticked for new admissions,
     * which come in PROVISIONAL.
     */
    public bool $confirmExisting = false;

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

            Action::make('downloadTemplate')
                ->label('CSV template')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $path = StudentCsvImporter::generateTemplate(auth()->user()?->school_id);

                    return response()->download(
                        $path,
                        'student-import-template.csv'
                    );
                }),

            Action::make('importStudents')
                ->label('Import students')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalHeading('Import Students')
                ->modalWidth('5xl')
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->mountUsing(function () {
                    $this->resetImport();
                })
                ->modalContent(function (): View {
                    return view(
                        'filament.resources.students.pages.import-modal',
                        [
                            'importStep' => $this->importStep,
                            'csvFile' => $this->csvFile,
                            'importId' => $this->importId,
                            'runInBackground' => $this->runInBackground,
                            'confirmExisting' => $this->confirmExisting,
                            'validationResult' => $this->validationResult,
                            'importProgress' => $this->importProgress,
                        ]
                    );
                }),

            // Admission as a modal, one step at a time — same fields as
            // editing a student, just presented one group at a time.
            CreateAction::make()
                ->label('New student')
                ->modalHeading('New student')
                ->modalWidth('7xl')
                ->extraModalWindowAttributes(['class' => 'sh-admission-modal'])
                // The School field is only shown to the platform owner.
                ->mutateDataUsing(fn (array $data): array => [
                    ...$data,
                    'school_id' => $data['school_id'] ?? auth()->user()->school_id,
                ])
                ->disabled(fn () => SubscriptionManager::roomForStudents(auth()->user()->school_id) === 0)
                ->tooltip(fn () => SubscriptionManager::roomForStudents(auth()->user()->school_id) === 0
                    ? 'The school has as many active students as its plan allows. Mark students who have left as Withdrawn/Transferred/Completed, or ask for a bigger plan on the Subscription page.'
                    : null)
                ->steps([
                    Step::make('Student Details')
                        ->description('Name, photo and identifiers')
                        ->icon('heroicon-o-identification')
                        // Photo on the left (2/6), details on the right (4/6).
                        ->columns(['default' => 1, 'md' => 6])
                        ->schema(StudentForm::identityFields()),

                    Step::make('Class & Enrollment')
                        ->description('Where this student belongs')
                        ->icon('heroicon-o-academic-cap')
                        ->columns(2)
                        ->schema(StudentForm::enrollmentFields()),

                    Step::make('Parent & Contact')
                        ->description('Guardian and contact details')
                        ->icon('heroicon-o-user-group')
                        ->columns(2)
                        ->schema(StudentForm::contactFields()),

                    Step::make('Address & Welfare')
                        ->description('Optional — can be added later')
                        ->icon('heroicon-o-heart')
                        ->columns(2)
                        ->schema(StudentForm::welfareFields()),
                ])
                ->successNotification(fn (Student $record) => Notification::make()
                    ->title('Student admitted')
                    ->body($record->name.' ('.$record->admission_no.') has been added.')
                    ->success()
                    ->actions([
                        Action::make('printAdmissionLetter')
                            ->label('Print admission letter')
                            ->button()
                            ->url(route('filament.app.students.admission-letter', $record))
                            ->openUrlInNewTab(),
                    ])),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Upload & Validate
    |--------------------------------------------------------------------------
    */

    public function uploadAndValidate(): void
    {
        $this->validate([
            'csvFile' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:10240',
            ],
        ]);

        $this->importStep = 'validating';

        $path = $this->csvFile->store('imports', 'local');
        $fileName = $this->csvFile->getClientOriginalName();

        $import = StudentImport::create([
            'school_id' => auth()->user()->school_id,
            'imported_by' => auth()->id(),
            'file_name' => $fileName,
            'file_path' => $path,
            'status' => 'pending',
            'confirm_on_import' => $this->confirmExisting,
        ]);

        $this->importId = $import->id;

        $importer = new StudentCsvImporter($import);
        $this->validationResult = $importer->validate();

        $this->importStep = 'preview';
    }

    /*
    |--------------------------------------------------------------------------
    | Start Import
    |--------------------------------------------------------------------------
    */

    public function startImport(): void
    {
        if (! $this->importId) {
            return;
        }

        $import = StudentImport::find($this->importId);

        if (! $import || $import->status !== 'validated') {
            Notification::make()
                ->title('Import not ready')
                ->body('Please validate the file first.')
                ->danger()
                ->send();

            return;
        }

        // Persist the confirm-existing choice (in case it was toggled on the
        // preview step) so the queued job picks it up.
        $import->update(['confirm_on_import' => $this->confirmExisting]);

        $this->importStep = 'importing';

        if ($this->runInBackground) {
            ProcessStudentImport::dispatch($import);
            $this->importProgress['status'] = 'importing';
        } else {
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

        $import = StudentImport::find($this->importId);

        if (! $import) {
            return;
        }

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
        $this->confirmExisting = false;
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
            return $seconds.'s';
        }

        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;

        return "{$minutes}m {$secs}s";
    }
}
