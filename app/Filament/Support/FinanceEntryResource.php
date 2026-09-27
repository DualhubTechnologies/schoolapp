<?php

namespace App\Filament\Support;

use App\Models\FinanceCategory;
use App\Models\FinanceEntry;
use App\Models\Term;
use App\Support\FinanceAccess;
use App\Support\PrivateFiles;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Shared by Expenses and Other Income: the same entry, in two directions.
 * Subclasses set $entryType and their labels.
 */
abstract class FinanceEntryResource extends Resource
{
    use \App\Filament\Concerns\GatedByModule;

    protected static ?string $model = FinanceEntry::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    /** income | expense */
    protected static string $entryType = 'expense';

    public static function isExpense(): bool
    {
        return static::$entryType === 'expense';
    }

    public static function getEloquentQuery(): Builder
    {
        FinanceCategory::ensureDefaults(auth()->user()->school_id);

        return FinanceEntry::withVoided()
            ->where('finance_entries.school_id', auth()->user()?->school_id)
            ->where('finance_entries.type', static::$entryType)
            ->with('category');
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record?->voucher_no;
    }

    public static function form(Schema $schema): Schema
    {
        $expense = static::isExpense();

        return $schema->columns(2)->components([
            DatePicker::make('entry_date')
                ->label('Date')
                ->native(false)
                ->displayFormat('j M Y')
                ->default(today())
                ->maxDate(today())
                ->required(),
            TextInput::make('amount')
                ->label('Amount')
                ->prefix('UGX')
                ->numeric()
                ->minValue(1)
                ->required(),
            Select::make('finance_category_id')
                ->label('Category')
                ->options(fn () => static::categoryOptions())
                ->searchable()
                ->required()
                ->helperText('Not in the list? Click + to add a category.')
                ->createOptionModalHeading('New ' . ($expense ? 'expense' : 'income') . ' category')
                ->createOptionForm([
                    TextInput::make('name')->label('Category name')->required()->maxLength(100),
                ])
                ->createOptionUsing(fn (array $data): int => FinanceCategory::firstOrCreate(
                    ['school_id' => auth()->user()->school_id, 'type' => static::$entryType, 'name' => trim($data['name'])],
                    ['sort_order' => 50, 'is_active' => true],
                )->getKey())
                ->columnSpanFull(),
            TextInput::make('party')
                ->label($expense ? 'Paid to' : 'Received from')
                ->placeholder($expense ? 'e.g. UMEME, Kakira Sugar, John the plumber' : 'e.g. Ministry of Education, PTA, Parent')
                ->maxLength(150),
            TextInput::make('description')
                ->placeholder($expense ? 'e.g. Electricity bill — September' : 'e.g. USE capitation grant — Term 3')
                ->required()
                ->maxLength(255),
            ToggleButtons::make('method')
                ->label($expense ? 'Paid by' : 'Received by')
                ->options(FinanceEntry::METHODS)
                ->default('cash')
                ->inline()
                ->live()
                ->required()
                ->columnSpanFull(),
            TextInput::make('reference')
                ->label(fn (Get $get) => match ($get('method')) {
                    'cheque' => 'Cheque number',
                    'mobile_money' => 'Transaction ID',
                    'bank' => 'Bank reference',
                    default => 'Receipt / invoice no. (optional)',
                })
                ->required(fn (Get $get) => in_array($get('method'), ['cheque', 'mobile_money', 'bank'], true))
                ->maxLength(100),
            FileUpload::make('attachment')
                ->label($expense ? 'Receipt / invoice (scan or photo)' : 'Supporting document')
                ->disk(PrivateFiles::DISK)
                ->visibility('private')
                ->directory('finance')
                ->acceptedFileTypes(['image/*', 'application/pdf'])
                ->maxSize(5120)
                ->openable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $expense = static::isExpense();

        return $table
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('entry_date')->orderByDesc('voucher_seq'))
            ->columns([
                TextColumn::make('voucher_no')
                    ->label('Voucher')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->color(fn (FinanceEntry $r) => $r->isVoided() ? 'gray' : null)
                    ->description(fn (FinanceEntry $r) => $r->isVoided() ? 'VOID — ' . $r->void_reason : null),
                TextColumn::make('entry_date')->label('Date')->date('j M Y')->sortable(),
                TextColumn::make('category.name')->label('Category')->badge()->color($expense ? 'warning' : 'success')->wrap(),
                TextColumn::make('description')
                    ->searchable(['description', 'party'])
                    ->description(fn (FinanceEntry $r) => $r->party)
                    ->wrap(),
                TextColumn::make('method')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state) => FinanceEntry::METHODS[$state] ?? $state)
                    ->description(fn (FinanceEntry $r) => $r->reference),
                TextColumn::make('recorded_by')->label('Recorded by')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('amount')
                    ->label('Amount (UGX)')
                    ->numeric()
                    ->alignEnd()
                    ->weight(FontWeight::Bold)
                    ->color(fn (FinanceEntry $r) => $r->isVoided() ? 'gray' : ($expense ? 'danger' : 'success'))
                    ->extraAttributes(fn (FinanceEntry $r) => $r->isVoided() ? ['style' => 'text-decoration: line-through'] : [])
                    ->summarize(Sum::make()->label('Total')->numeric()->query(fn ($query) => $query->whereNull('voided_at'))),
            ])
            ->filters([
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->native(false)->displayFormat('j M Y'),
                        DatePicker::make('until')->label('To')->native(false)->displayFormat('j M Y'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('entry_date', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('entry_date', '<=', $d)))
                    ->indicateUsing(fn (array $data) => ($data['from'] ?? null) || ($data['until'] ?? null)
                        ? trim(($data['from'] ? Carbon::parse($data['from'])->format('j M Y') : '…') . ' – ' . ($data['until'] ? Carbon::parse($data['until'])->format('j M Y') : '…'))
                        : null),
                SelectFilter::make('finance_category_id')->label('Category')->options(fn () => static::categoryOptions()),
                SelectFilter::make('term_id')->label('Term')
                    ->options(fn () => Term::where('school_id', auth()->user()?->school_id)->with('academicYear')->get()->mapWithKeys(fn ($t) => [$t->id => $t->label()])->all()),
                SelectFilter::make('method')->options(FinanceEntry::METHODS),
                SelectFilter::make('status')
                    ->options(['valid' => 'Valid', 'void' => 'Voided'])
                    ->default('valid')
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'valid' => $query->whereNull('voided_at'),
                        'void' => $query->whereNotNull('voided_at'),
                        default => $query,
                    }),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                Action::make('voucher')
                    ->label('Voucher')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (FinanceEntry $r) => route('filament.app.finance.voucher', $r->getKey()), shouldOpenInNewTab: true),
                ActionGroup::make([
                    Action::make('attachment')
                        ->label('View attachment')
                        ->icon('heroicon-o-paper-clip')
                        ->visible(fn (FinanceEntry $r) => filled($r->attachment))
                        ->url(fn (FinanceEntry $r) => PrivateFiles::url($r->attachment), shouldOpenInNewTab: true),
                    EditAction::make()->visible(fn (FinanceEntry $r) => ! $r->isVoided()),
                    Action::make('void')
                        ->label('Void')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->visible(fn (FinanceEntry $r) => ! $r->isVoided())
                        ->requiresConfirmation()
                        ->modalHeading(fn (FinanceEntry $r) => "Void {$r->voucher_no}?")
                        ->modalDescription('It stays on record marked VOID and stops counting in every total.')
                        ->schema([TextInput::make('reason')->required()->maxLength(200)])
                        ->action(function (FinanceEntry $r, array $data) {
                            $r->void($data['reason']);
                            Notification::make()->title("{$r->voucher_no} voided")->success()->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('Nothing recorded yet')
            ->paginationPageOptions([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    /** @return array<int, string> Categories typed entries can use (not the automatic ones). */
    protected static function categoryOptions(): array
    {
        return FinanceCategory::where('school_id', auth()->user()?->school_id)
            ->where('type', static::$entryType)
            ->whereNull('system_key')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function canViewAny(): bool
    {
        return FinanceAccess::allowed();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
