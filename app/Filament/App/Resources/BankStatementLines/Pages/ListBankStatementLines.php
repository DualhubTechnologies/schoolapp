<?php

namespace App\Filament\App\Resources\BankStatementLines\Pages;

use App\Filament\App\Resources\BankAccounts\BankAccountResource;
use App\Filament\App\Resources\BankStatementLines\BankStatementLineResource;
use App\Models\BankAccount;
use App\Services\Banking\BankReconciliation;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\Url;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

/**
 * One bank account's statement at a time (?account=), with what is still
 * to reconcile above the list.
 */
class ListBankStatementLines extends ListRecords
{
    protected static string $resource = BankStatementLineResource::class;

    #[Url]
    public ?int $account = null;

    public function getTitle(): string
    {
        return 'Bank reconciliation';
    }

    public function bankAccount(): ?BankAccount
    {
        $accounts = BankAccount::where('school_id', auth()->user()?->school_id)->orderByDesc('is_active')->orderBy('name');

        return ($this->account ? (clone $accounts)->whereKey($this->account)->first() : null) ?? $accounts->first();
    }

    public function getSubheading(): ?string
    {
        $account = $this->bankAccount();

        if (! $account) {
            return 'Add the school\'s bank account first (Finance → Bank accounts).';
        }

        $summary = app(BankReconciliation::class)->summary($account);

        return $account->label().' · '
            .($summary['statement_balance'] !== null ? 'Balance on last statement: UGX '.number_format($summary['statement_balance']).' · ' : '')
            .($summary['unmatched'] > 0
                ? "{$summary['unmatched']} lines to reconcile (UGX ".number_format($summary['unmatched_in']).' in, UGX '.number_format($summary['unmatched_out']).' out)'
                : 'Everything imported is reconciled.');
    }

    protected function getHeaderActions(): array
    {
        $accounts = BankAccount::where('school_id', auth()->user()?->school_id)->orderBy('name')->get();

        return [
            Action::make('import')
                ->label('Import statement')
                ->icon('heroicon-o-arrow-up-tray')
                ->visible(fn (): bool => $this->bankAccount() !== null)
                ->modalHeading(fn (): string => 'Import statement: '.$this->bankAccount()?->label())
                ->modalDescription('Download the statement from Centenary internet banking (CenteOnline) as Excel or CSV and upload it here. Importing the same statement twice is safe: lines already in are skipped.')
                ->schema([
                    FileUpload::make('file')
                        ->label('Statement file')
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/csv',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->maxSize(10240)
                        ->storeFiles(false)
                        ->required(),
                ])
                ->modalSubmitActionLabel('Import and match')
                ->action(function (array $data, BankReconciliation $reconciliation): void {
                    $account = $this->bankAccount();
                    $file = $data['file'] ?? null;

                    if (! $account || ! $file instanceof TemporaryUploadedFile) {
                        return;
                    }

                    try {
                        $counts = $reconciliation->import($account, $file->getRealPath(), $file->getClientOriginalName());
                    } catch (RuntimeException $e) {
                        Notification::make()->title('Could not import the statement')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    }

                    $matched = $reconciliation->autoMatch($account);

                    Notification::make()
                        ->title("{$counts['added']} new statement ".str('line')->plural($counts['added']))
                        ->body(($counts['already'] > 0 ? "{$counts['already']} were already imported. " : '')."{$matched} matched to SchoolHub by themselves.")
                        ->success()
                        ->send();
                }),

            Action::make('autoMatch')
                ->label('Match automatically')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->visible(fn (): bool => $this->bankAccount() !== null)
                ->action(function (BankReconciliation $reconciliation): void {
                    $account = $this->bankAccount();

                    if (! $account) {
                        return;
                    }

                    $matched = $reconciliation->autoMatch($account);

                    Notification::make()
                        ->title($matched > 0 ? "{$matched} ".str('line')->plural($matched).' matched' : 'Nothing more could be matched by itself')
                        ->body($matched > 0 ? null : 'Use Match on each remaining line, record it, or mark it explained.')
                        ->success()
                        ->send();
                }),

            ActionGroup::make([
                ...$accounts->map(fn (BankAccount $account): Action => Action::make('account'.$account->getKey())
                    ->label($account->label())
                    ->url(static::getUrl(['account' => $account->getKey()])))->all(),
                Action::make('manageAccounts')
                    ->label('Manage bank accounts')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url(BankAccountResource::getUrl()),
            ])
                ->label('Account')
                ->icon('heroicon-o-building-library')
                ->color('gray')
                ->button(),
        ];
    }
}
