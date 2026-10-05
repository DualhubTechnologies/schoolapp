<?php

namespace App\Filament\App\Resources\BankAccounts;

use App\Filament\App\Resources\BankAccounts\Pages\ManageBankAccounts;
use App\Filament\App\Resources\BankStatementLines\Pages\ListBankStatementLines;
use App\Filament\Concerns\GatedByModule;
use App\Filament\Support\ConfirmWithPassword;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Support\FinanceAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The school's bank accounts, whose statements are reconciled on the Bank
 * reconciliation page.
 */
class BankAccountResource extends Resource
{
    use GatedByModule;

    protected static ?string $model = BankAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Bank accounts';

    protected static ?string $modelLabel = 'bank account';

    protected static ?string $slug = 'bank-accounts';

    /** Banks schools in Uganda use; another can be typed in the account name. */
    public const BANKS = [
        'Centenary Bank' => 'Centenary Bank',
        'Stanbic Bank' => 'Stanbic Bank',
        'DFCU Bank' => 'DFCU Bank',
        'Equity Bank' => 'Equity Bank',
        'Absa Bank' => 'Absa Bank',
        'Bank of Africa' => 'Bank of Africa',
        'Post Bank' => 'PostBank Uganda',
        'Housing Finance Bank' => 'Housing Finance Bank',
        'Pride Bank' => 'Pride Bank',
        'Other' => 'Other',
    ];

    public static function canViewAny(): bool
    {
        return FinanceAccess::allowed() && filled(auth()->user()?->school_id);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('school_id', auth()->user()?->school_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')
                ->label('Account name')
                ->placeholder('e.g. School fees account')
                ->required()
                ->maxLength(100),
            Select::make('bank')
                ->options(self::BANKS)
                ->default('Centenary Bank')
                ->selectablePlaceholder(false)
                ->required(),
            TextInput::make('account_number')
                ->label('Account number')
                ->maxLength(40),
            TextInput::make('opening_balance')
                ->label('Balance on the opening date')
                ->prefix('UGX')
                ->numeric()
                ->default(0)
                ->required(),
            DatePicker::make('opening_date')
                ->label('Opening date')
                ->native(false)
                ->displayFormat('j M Y')
                ->helperText('The day you start reconciling from.'),
            Toggle::make('is_active')
                ->label('In use')
                ->default(true)
                ->inline(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Account')
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (BankAccount $record): string => $record->bank.($record->account_number ? ' · '.$record->account_number : '')),
                TextColumn::make('statement_balance')
                    ->label('Balance on last statement')
                    ->state(fn (BankAccount $record): ?float => ($balance = $record->statementLines()->whereNotNull('balance')->orderByDesc('line_date')->orderByDesc('id')->value('balance')) !== null ? (float) $balance : null)
                    ->numeric()
                    ->prefix('UGX ')
                    ->placeholder('No statement yet'),
                TextColumn::make('unmatched')
                    ->label('Lines to reconcile')
                    ->state(fn (BankAccount $record): int => $record->statementLines()->where('status', 'unmatched')->count())
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'success'),
                TextColumn::make('is_active')
                    ->label('')
                    ->formatStateUsing(fn (bool $state): string => $state ? '' : 'Not in use')
                    ->color('gray'),
            ])
            ->recordActions([
                Action::make('reconcile')
                    ->label('Reconcile')
                    ->icon('heroicon-o-scale')
                    ->url(fn (BankAccount $record): string => ListBankStatementLines::getUrl(['account' => $record->getKey()])),
                EditAction::make(),
                ConfirmWithPassword::on(DeleteAction::make()
                    ->modalDescription(fn (BankAccount $record): string => 'Deletes the account and its '.BankStatementLine::where('bank_account_id', $record->getKey())->count().' imported statement lines. Receipts and vouchers are not touched.')),
            ])
            ->emptyStateHeading('No bank accounts yet')
            ->emptyStateDescription('Add the school\'s bank account, then import its statement to reconcile it with SchoolHub.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageBankAccounts::route('/')];
    }
}
