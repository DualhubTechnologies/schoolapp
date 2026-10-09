<?php

namespace App\Filament\App\Resources\Students\Pages;

use App\Filament\App\Resources\Students\Schemas\StudentForm;
use App\Filament\App\Resources\Students\StudentResource;
use App\Jobs\ProcessStudentImport;
use App\Models\Student;
use App\Models\StudentImport;
use App\Services\BillingService;
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

    /** Fees put on the account of the learner just admitted (term already billed). */
    public float $billedOnAdmission = 0;

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
                ->visible(fn (): bool => StudentResource::canCreate())
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
                ->visible(fn (): bool => StudentResource::canCreate())
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

            // Quick admission: one short screen with what fees, marks and
            // parents need. "Save & add more details" opens the learner's
            // full profile for the photo, LIN, house, address and so on.
            CreateAction::make()
                ->label('New student')
                ->visible(fn (): bool => StudentResource::canCreate())
                ->modalHeading('Admit a learner')
                ->modalDescription('Just the essentials. Everything else can be added on the learner\'s profile at any time.')
                ->modalWidth('3xl')
                ->schema(StudentForm::quickFields())
                ->mutateDataUsing(function (array $data): array {
                    $schoolId = $data['school_id'] ?? auth()->user()->school_id;

                    return StudentForm::resolveQuickGuardian([
                        ...$data,
                        'school_id' => $schoolId,
                        'admission_date' => $data['admission_date'] ?? now()->toDateString(),
                        'status' => $data['status'] ?? 'active',
                    ], (int) $schoolId);
                })
                // Admitted after the term was billed: bill them now.
                ->after(function (Student $record): void {
                    $this->billedOnAdmission = app(BillingService::class)->billNewLearner($record);
                })
                ->modalSubmitActionLabel('Admit')
                ->extraModalFooterActions(fn (CreateAction $action): array => [
                    $action->makeModalSubmitAction('admitAndEdit', arguments: ['edit' => true])
                        ->label('Admit & add more details')
                        ->color('gray'),
                ])
                ->successRedirectUrl(fn (Student $record, array $arguments): ?string => ($arguments['edit'] ?? false)
                    ? StudentResource::getUrl('edit', ['record' => $record])
                    : null)
                ->disabled(fn () => SubscriptionManager::roomForStudents(auth()->user()->school_id) === 0)
                ->tooltip(fn () => SubscriptionManager::roomForStudents(auth()->user()->school_id) === 0
                    ? 'The school has as many active students as its plan allows. Mark students who have left as Withdrawn/Transferred/Completed, or ask for a bigger plan on the Subscription page.'
                    : null)
                ->successNotification(fn (Student $record) => Notification::make()
                    ->title('Student admitted')
                    ->body($record->name.' ('.$record->admission_no.') has been added.'
                        .($this->billedOnAdmission > 0 ? ' This term\'s fees of UGX '.number_format($this->billedOnAdmission).' are on their account.' : ''))
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
        abort_unless(StudentResource::canCreate(), 403);

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

    /**
     * Check the same file again -- after the school has moved to a bigger
     * plan in another tab, say -- without uploading it a second time.
     */
    public function recheckImport(): void
    {
        $import = $this->importId ? StudentImport::find($this->importId) : null;

        if (! $import || $import->school_id !== auth()->user()->school_id) {
            $this->resetImport();

            return;
        }

        $this->validationResult = (new StudentCsvImporter($import))->validate();
    }

    /*
    |--------------------------------------------------------------------------
    | Start Import
    |--------------------------------------------------------------------------
    */

    public function startImport(): void
    {
        abort_unless(StudentResource::canCreate(), 403);

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

        // More new students than the plan has room for: nothing is imported.
        // The school moves to a plan that fits, or trims the file. Checked
        // again here, as students may have been added since the preview.
        $room = SubscriptionManager::roomForStudents($import->school_id);
        $newRows = (int) ($this->validationResult['valid_rows'] ?? 0);

        if ($room !== null && $newRows > $room) {
            Notification::make()
                ->title('Too many students for your plan')
                ->body('Your plan has room for '.number_format($room).' more active '.str('student')->plural($room).' and this file has '.number_format($newRows).' new. Choose a bigger plan, or remove '.number_format($newRows - $room).' from the file and upload it again.')
                ->danger()
                ->persistent()
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
