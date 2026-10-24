<?php

namespace App\Filament\App\Resources\FeeBalances\Pages;

use App\Filament\App\Resources\FeeBalances\FeeBalanceResource;
use App\Models\Term;
use App\Services\OpeningBalanceCsvImporter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ListFeeBalances extends ListRecords
{
    use WithFileUploads;

    protected static string $resource = FeeBalanceResource::class;

    /** The CSV of opening balances being uploaded. */
    public ?TemporaryUploadedFile $csvFile = null;

    /**
     * Where the checked file waits for "Import" (local disk). Locked: the
     * import reads and then deletes this path, so it must never come from
     * the browser.
     */
    #[Locked]
    public ?string $storedFile = null;

    /** The term the balances are carried into (an id from the dropdown). */
    public int|string|null $openingTermId = null;

    /** The old system had already charged that term's fees. */
    public bool $includesTermFees = false;

    /** upload -> preview -> complete */
    public string $importStep = 'upload';

    /** @var array<string, mixed> */
    public array $validationResult = [];

    /** @var array<string, mixed> */
    public array $importResult = [];

    public function getTitle(): string
    {
        return 'Student Accounts';
    }

    public function getSubheading(): ?string
    {
        return "Every student's fee account. Click a student to open their statement; select several to send reminders.";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadOpeningBalances')
                ->label('Opening balances sheet')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => response()->download(
                    OpeningBalanceCsvImporter::generateTemplate((int) auth()->user()?->school_id),
                    'opening-balances.csv',
                )),

            Action::make('importOpeningBalances')
                ->label('Import opening balances')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalHeading('Import opening balances')
                ->modalWidth('5xl')
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->mountUsing(fn () => $this->resetImport())
                ->modalContent(fn (): View => view('filament.resources.fee-balances.pages.opening-balances-modal', [
                    'importStep' => $this->importStep,
                    'csvFile' => $this->csvFile,
                    'terms' => $this->termOptions(),
                    'termLabel' => $this->openingTerm()?->label(),
                    'includesTermFees' => $this->includesTermFees,
                    'validationResult' => $this->validationResult,
                    'importResult' => $this->importResult,
                ])),
        ];
    }

    public function uploadAndValidate(): void
    {
        $this->validate([
            'csvFile' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $term = $this->openingTerm();

        if (! $term) {
            $this->addError('openingTermId', 'Choose the term to carry the balances into.');

            return;
        }

        $stored = $this->csvFile?->store('imports', 'local');

        if (! is_string($stored)) {
            $this->addError('csvFile', 'The file could not be saved. Please try again.');

            return;
        }

        $this->storedFile = $stored;
        $this->validationResult = $this->importer()->validate($this->storedFile, $term, $this->includesTermFees);
        $this->importStep = 'preview';
    }

    public function startImport(): void
    {
        $term = $this->openingTerm();

        if (! $term || ! $this->storedFile || ! Storage::disk('local')->exists($this->storedFile)) {
            Notification::make()->title('Upload the file again')->body('The checked file is no longer available.')->danger()->send();
            $this->resetImport();

            return;
        }

        $this->importResult = $this->importer()->import($this->storedFile, $term, $this->includesTermFees);
        $this->storedFile = null;
        $this->importStep = 'complete';

        $saved = $this->importResult['added'] + $this->importResult['replaced'] + $this->importResult['removed'];

        Notification::make()
            ->title("{$saved} opening ".Str::plural('balance', $saved).' saved')
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
        $this->openingTermId = Term::current()?->getKey();
        $this->includesTermFees = false;
        $this->importStep = 'upload';
        $this->validationResult = [];
        $this->importResult = [];
    }

    protected function importer(): OpeningBalanceCsvImporter
    {
        return new OpeningBalanceCsvImporter((int) auth()->user()?->school_id);
    }

    /** The chosen term, only ever one of this school's. */
    protected function openingTerm(): ?Term
    {
        if (! is_numeric($this->openingTermId)) {
            return null;
        }

        return Term::query()
            ->where('school_id', auth()->user()?->school_id)
            ->with('academicYear')
            ->find((int) $this->openingTermId);
    }

    /** @return array<int, string> term id => "Term 3 — 2026", latest first */
    protected function termOptions(): array
    {
        return Term::query()
            ->where('school_id', auth()->user()?->school_id)
            ->with('academicYear')
            ->get()
            ->sortByDesc(fn (Term $term): string => $term->sortKey())
            ->mapWithKeys(fn (Term $term): array => [(int) $term->getKey() => $term->label().($term->is_current ? ' (current)' : '')])
            ->all();
    }
}
