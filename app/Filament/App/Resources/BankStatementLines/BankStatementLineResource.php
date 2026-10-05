<?php

namespace App\Filament\App\Resources\BankStatementLines;

use App\Filament\App\Resources\BankStatementLines\Pages\ListBankStatementLines;
use App\Filament\Concerns\GatedByModule;
use App\Models\BankStatementLine;
use App\Models\FinanceCategory;
use App\Models\FinanceEntry;
use App\Models\StudentPayment;
use App\Services\Banking\BankReconciliation;
use App\Support\FinanceAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Bank reconciliation: an account's imported statement, line by line, and
 * what each line is in SchoolHub. App\Services\Banking\BankReconciliation
 * does the work; the page picks the account (ListBankStatementLines).
 */
class BankStatementLineResource extends Resource
{
    use GatedByModule;

    protected static ?string $model = BankStatementLine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Bank reconciliation';

    protected static ?string $modelLabel = 'statement line';

    protected static ?string $pluralModelLabel = 'Bank reconciliation';

    protected static ?string $slug = 'bank-reconciliation';

    /** Common lines with no SchoolHub record by design. */
    public const EXPLANATIONS = [
        'Salaries (payroll)' => 'Salaries (payroll)',
        'Transfer between the school\'s accounts' => 'Transfer between the school\'s accounts',
        'SchoolPay settlement (receipts already recorded)' => 'SchoolPay settlement (receipts already recorded)',
        'Cash banked (receipts already recorded)' => 'Cash banked (receipts already recorded)',
        'Reversal by the bank' => 'Reversal by the bank',
    ];

    public static function canViewAny(): bool
    {
        return FinanceAccess::allowed() && filled(auth()->user()?->school_id);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('school_id', auth()->user()?->school_id)
            ->with('matchable');
    }

    public static function table(Table $table): Table
    {
        return $table
            // The page shows one account at a time (?account=).
            ->modifyQueryUsing(fn (Builder $query, $livewire): Builder => $livewire instanceof ListBankStatementLines
                ? $query->where('bank_account_id', $livewire->bankAccount()?->getKey() ?? 0)
                : $query)
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('line_date')->orderByDesc('id'))
            ->columns([
                TextColumn::make('line_date')
                    ->label('Date')
                    ->date('j M Y')
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Bank says')
                    ->description(fn (BankStatementLine $record): ?string => $record->reference ? "Ref. {$record->reference}" : null)
                    ->searchable(['description', 'reference'])
                    ->wrap(),
                TextColumn::make('money_in')
                    ->label('In (UGX)')
                    ->numeric()
                    ->alignEnd()
                    ->color('success')
                    ->formatStateUsing(fn ($state): string => (float) $state > 0 ? number_format((float) $state) : ''),
                TextColumn::make('money_out')
                    ->label('Out (UGX)')
                    ->numeric()
                    ->alignEnd()
                    ->color('danger')
                    ->formatStateUsing(fn ($state): string => (float) $state > 0 ? number_format((float) $state) : ''),
                TextColumn::make('balance')
                    ->label('Balance')
                    ->numeric()
                    ->alignEnd()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('In SchoolHub')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => BankStatementLine::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'matched' => 'success',
                        'explained' => 'info',
                        default => 'warning',
                    })
                    ->description(fn (BankStatementLine $record): ?string => $record->matchLabel())
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(BankStatementLine::STATUSES)
                    ->default('unmatched'),
            ])
            ->recordActions([
                static::matchAction(),
                ActionGroup::make([
                    static::recordAction(),
                    static::explainAction(),
                    Action::make('unmatch')
                        ->label('Undo match')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('gray')
                        ->visible(fn (BankStatementLine $record): bool => $record->status !== 'unmatched')
                        ->requiresConfirmation()
                        ->action(fn (BankStatementLine $record, BankReconciliation $reconciliation) => $reconciliation->unmatch($record)),
                ]),
            ])
            ->emptyStateHeading('Nothing to reconcile here')
            ->emptyStateDescription('Import the bank statement (CSV or Excel from internet banking). Lines SchoolHub cannot match by itself are listed here for you.');
    }

    /** Pick the receipt or voucher this line is. */
    public static function matchAction(): Action
    {
        return Action::make('match')
            ->label('Match')
            ->icon('heroicon-o-link')
            ->color('warning')
            ->visible(fn (BankStatementLine $record): bool => $record->status === 'unmatched')
            ->modalHeading(fn (BankStatementLine $record): string => ($record->isMoneyIn() ? 'Money in' : 'Money out').': UGX '.number_format($record->amount()))
            ->modalDescription(fn (BankStatementLine $record): string => $record->line_date->format('j M Y').' · '.$record->description)
            ->schema([
                Select::make('record')
                    ->label('Which record in SchoolHub is it?')
                    ->options(fn (BankStatementLine $record, BankReconciliation $reconciliation): array => $reconciliation->candidates($record)
                        ->mapWithKeys(fn (Model $candidate): array => [static::candidateKey($candidate) => static::candidateLabel($candidate)])
                        ->all())
                    ->helperText(fn (BankStatementLine $record, BankReconciliation $reconciliation): string => $reconciliation->candidates($record)->isEmpty()
                        ? 'No receipt or voucher of this amount within two weeks. Record it from here instead, or mark it explained.'
                        : 'Same amount, within two weeks of the bank date. Only bank, cheque and SchoolPay records are listed.')
                    ->required(),
            ])
            ->modalSubmitActionLabel('Match')
            ->action(function (BankStatementLine $record, array $data, BankReconciliation $reconciliation): void {
                [$type, $id] = array_pad(explode(':', (string) $data['record'], 2), 2, null);
                $chosen = match ($type) {
                    'payment' => StudentPayment::where('school_id', $record->school_id)->whereKey($id)->first(),
                    'entry' => FinanceEntry::where('school_id', $record->school_id)->whereKey($id)->first(),
                    default => null,
                };

                try {
                    if (! $chosen) {
                        throw new RuntimeException('That record was not found.');
                    }

                    $reconciliation->match($record, $chosen);
                } catch (RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Matched')->success()->send();
            });
    }

    /** Money only the bank knew about: record it as income or an expense. */
    public static function recordAction(): Action
    {
        return Action::make('record')
            ->label(fn (BankStatementLine $record): string => $record->isMoneyIn() ? 'Record as income' : 'Record as expense')
            ->icon('heroicon-o-plus-circle')
            ->visible(fn (BankStatementLine $record): bool => $record->status === 'unmatched')
            ->modalHeading(fn (BankStatementLine $record): string => ($record->isMoneyIn() ? 'Record income' : 'Record expense').' of UGX '.number_format($record->amount()))
            ->modalDescription('For money only the bank knew about, such as bank charges, interest or a deposit nobody recorded. It is saved as paid by bank and matched to this line.')
            ->fillForm(fn (BankStatementLine $record): array => [
                'description' => mb_substr($record->description, 0, 255),
                'finance_category_id' => FinanceCategory::where('school_id', $record->school_id)
                    ->where('type', $record->isMoneyIn() ? 'income' : 'expense')
                    ->where('name', 'like', $record->isMoneyIn() ? '%interest%' : '%bank%')
                    ->value('id'),
            ])
            ->schema([
                Select::make('finance_category_id')
                    ->label('Category')
                    ->options(function (BankStatementLine $record): array {
                        FinanceCategory::ensureDefaults($record->school_id);

                        return FinanceCategory::where('school_id', $record->school_id)
                            ->where('type', $record->isMoneyIn() ? 'income' : 'expense')
                            ->whereNull('system_key')
                            ->orderBy('sort_order')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->required(),
                TextInput::make('description')->required()->maxLength(255),
                TextInput::make('party')
                    ->label(fn (BankStatementLine $record): string => $record->isMoneyIn() ? 'Received from' : 'Paid to')
                    ->maxLength(150),
            ])
            ->action(function (BankStatementLine $record, array $data, BankReconciliation $reconciliation): void {
                try {
                    $entry = $reconciliation->recordEntry($record, (int) $data['finance_category_id'], $data['description'], $data['party'] ?? null);
                } catch (RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title("{$entry->voucher_no} recorded and matched")->success()->send();
            });
    }

    /** No SchoolHub record by design: salaries, transfers... */
    public static function explainAction(): Action
    {
        return Action::make('explain')
            ->label('Mark explained')
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->visible(fn (BankStatementLine $record): bool => $record->status === 'unmatched')
            ->schema([
                Select::make('reason')
                    ->label('Why there is no single SchoolHub record')
                    ->options(self::EXPLANATIONS + ['other' => 'Something else'])
                    ->live()
                    ->required(),
                TextInput::make('note')
                    ->label('Explain')
                    ->maxLength(255)
                    ->visible(fn ($get): bool => $get('reason') === 'other')
                    ->required(fn ($get): bool => $get('reason') === 'other'),
            ])
            ->action(function (BankStatementLine $record, array $data, BankReconciliation $reconciliation): void {
                $reconciliation->explain($record, $data['reason'] === 'other' ? (string) $data['note'] : (string) $data['reason']);

                Notification::make()->title('Marked explained')->success()->send();
            });
    }

    public static function candidateKey(Model $record): string
    {
        return ($record instanceof StudentPayment ? 'payment:' : 'entry:').$record->getKey();
    }

    public static function candidateLabel(Model $record): string
    {
        if ($record instanceof StudentPayment) {
            return "Receipt {$record->receipt_no} · {$record->paid_on->format('j M')} · ".($record->student->name ?? 'Learner').' · '.$record->methodLabel();
        }

        return $record->getAttribute('voucher_no').' · '.$record->getAttribute('entry_date')?->format('j M').' · '.($record->getAttribute('party') ?: $record->getAttribute('description'));
    }

    public static function getPages(): array
    {
        return ['index' => ListBankStatementLines::route('/')];
    }
}
