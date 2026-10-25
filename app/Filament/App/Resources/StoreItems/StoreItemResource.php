<?php

namespace App\Filament\App\Resources\StoreItems;

use App\Filament\App\Resources\StoreItems\Pages\ManageStoreItems;
use App\Filament\App\Resources\StoreMovements\StoreMovementResource;
use App\Filament\Concerns\GatedByModule;
use App\Filament\Concerns\PlacedInMenu;
use App\Models\FinanceCategory;
use App\Models\FinanceEntry;
use App\Models\StoreItem;
use App\Services\Stores\StoreLedger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use RuntimeException;

/**
 * The school stores: what is kept (posho, beans, sugar, stationery,
 * soap), how much is left and what it is worth. Stock is received from
 * suppliers (recorded as an expense too, if wished), issued to the
 * kitchen, departments or people, and corrected after a count, all
 * through StoreLedger so every change has a line on the stock card.
 */
class StoreItemResource extends Resource
{
    use GatedByModule;
    use PlacedInMenu;

    protected static ?string $model = StoreItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $modelLabel = 'store item';

    protected static ?string $slug = 'stores/items';

    /** Where stock is usually issued to; any other place can be typed. */
    public const ISSUED_TO = ['Kitchen', 'Boys\' dormitory', 'Girls\' dormitory', 'Staff room', 'Head teacher\'s office', 'Bursar\'s office', 'Science laboratory', 'Library', 'Sick bay', 'Games department', 'Cleaners', 'Farm'];

    /** The expense category a store category is usually paid from. */
    protected const EXPENSE_FOR = [
        'food' => 'Feeding & meals',
        'kitchen' => 'Feeding & meals',
        'stationery' => 'Stationery & printing',
        'teaching' => 'Teaching & learning materials',
        'repairs' => 'Repairs & maintenance',
        'sports' => 'Sports & co-curricular',
        'cleaning' => 'Administration & office',
        'uniform' => 'Other expenses',
        'other' => 'Other expenses',
    ];

    public static function getNavigationBadge(): ?string
    {
        $low = StoreItem::where('school_id', auth()->user()?->school_id)
            ->where('is_active', true)
            ->whereNotNull('reorder_level')
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->count();

        return $low ? (string) $low : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Running low';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('school_id', auth()->user()?->school_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->placeholder('e.g. Maize flour (posho)')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('school_id', auth()->user()?->school_id))
                ->validationMessages(['unique' => 'This item is already in the stores list.'])
                ->columnSpanFull(),
            Select::make('category')->options(StoreItem::CATEGORIES)->default('food')->required()->native(false),
            TextInput::make('unit')->default('kg')->required()->maxLength(30)->datalist(StoreItem::UNITS)->placeholder('kg, bags, litres, pieces…'),
            TextInput::make('reorder_level')
                ->label('Warn when down to')
                ->numeric()
                ->minValue(0)
                ->placeholder('e.g. 100')
                ->helperText('The menu shows a warning when the store reaches this.'),
            TextInput::make('unit_cost')->label('Price per unit (UGX)')->numeric()->minValue(0)->placeholder('Updated each time stock is received'),
            Toggle::make('is_active')->label('Still kept')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->description(function (): string {
                $items = StoreItem::where('school_id', auth()->user()?->school_id)->where('is_active', true)->get();
                $value = $items->sum(fn (StoreItem $item): float => (float) $item->value());
                $low = $items->filter->isLow()->count();

                return 'Stock in the stores is worth about UGX '.number_format($value).' at the last prices paid'.($low ? " · {$low} running low" : '').'.';
            })
            ->columns([
                TextColumn::make('name')->weight('semibold')->searchable()->sortable()
                    ->description(fn (StoreItem $record): string => StoreItem::CATEGORIES[$record->category] ?? $record->category),
                TextColumn::make('quantity')
                    ->label('In the store')
                    ->formatStateUsing(fn (StoreItem $record): string => $record->amount())
                    ->badge()
                    ->color(fn (StoreItem $record): string => (float) $record->quantity <= 0 ? 'danger' : ($record->isLow() ? 'warning' : 'success'))
                    ->sortable(),
                TextColumn::make('reorder_level')->label('Warn at')->placeholder('-')
                    ->formatStateUsing(fn (StoreItem $record): string => $record->amount($record->reorder_level)),
                TextColumn::make('unit_cost')->label('Last price')->placeholder('-')
                    ->formatStateUsing(fn (StoreItem $record): string => 'UGX '.number_format((float) $record->unit_cost).' / '.$record->singularUnit()),
                TextColumn::make('value')->label('Worth')->placeholder('-')
                    ->state(fn (StoreItem $record): ?string => $record->value() === null ? null : 'UGX '.number_format((float) $record->value())),
                IconColumn::make('is_active')->label('Kept')->boolean()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')->options(StoreItem::CATEGORIES),
                TernaryFilter::make('low')
                    ->label('Stock level')
                    ->placeholder('All items')
                    ->trueLabel('Running low or out')
                    ->falseLabel('Enough in the store')
                    ->queries(
                        true: fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('quantity', '<=', 0)->orWhere(fn (Builder $q) => $q->whereNotNull('reorder_level')->whereColumn('quantity', '<=', 'reorder_level'))),
                        false: fn (Builder $query) => $query->where('quantity', '>', 0)->where(fn (Builder $q) => $q->whereNull('reorder_level')->orWhereColumn('quantity', '>', 'reorder_level')),
                    ),
                TernaryFilter::make('is_active')->label('Still kept')->default(true),
            ])
            ->recordActions([
                static::receiveAction(),
                static::issueAction(),
                ActionGroup::make([
                    static::countAction(),
                    Action::make('card')
                        ->label('Stock card')
                        ->icon('heroicon-o-queue-list')
                        ->url(fn (StoreItem $record): string => StoreMovementResource::getUrl('index', ['filters' => ['store_item_id' => ['value' => $record->getKey()]]])),
                    EditAction::make(),
                    DeleteAction::make()
                        ->visible(fn (StoreItem $record): bool => ! $record->movements()->exists())
                        ->modalDescription('Items with stock movements cannot be deleted; switch off "Still kept" instead.'),
                ]),
            ])
            ->emptyStateHeading('Nothing in the stores list yet')
            ->emptyStateDescription('Add each thing the stores keep (posho, beans, sugar, soap, exercise books), then receive stock as it is delivered and issue it as it is used.');
    }

    /**
     * Stock delivered. On a row it is for that item; in the page header the
     * item is chosen first.
     */
    public static function receiveAction(bool $chooseItem = false): Action
    {
        return Action::make($chooseItem ? 'receiveAny' : 'receive')
            ->label('Receive stock')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->modalHeading(fn (?StoreItem $record): string => $record ? 'Receive '.$record->name : 'Receive stock')
            ->modalSubmitActionLabel('Receive')
            ->schema(fn (?StoreItem $record): array => [
                ...($chooseItem ? [static::itemSelect()] : []),
                Grid::make(2)->schema([
                    TextInput::make('quantity')
                        ->label(fn (Get $get): string => 'Quantity received ('.(static::chosen($record, $get)->unit ?? 'units').')')
                        ->numeric()->minValue(0.01)->required()->live(onBlur: true),
                    TextInput::make('unit_cost')
                        ->label(fn (Get $get): string => 'Price per '.(static::chosen($record, $get)?->singularUnit() ?? 'unit').' (UGX)')
                        ->numeric()->minValue(0)->live(onBlur: true)
                        ->default(fn (): ?string => $record?->unit_cost)
                        ->helperText(fn (Get $get): ?string => is_numeric($get('quantity')) && is_numeric($get('unit_cost'))
                            ? 'Total: UGX '.number_format((float) $get('quantity') * (float) $get('unit_cost'))
                            : null),
                    DatePicker::make('date')->label('Received on')->default(today())->maxDate(today())->required()->native(false),
                    TextInput::make('supplier')->maxLength(255)->placeholder('Who delivered it'),
                    TextInput::make('reference')->label('Delivery note / invoice no.')->maxLength(255)->columnSpanFull(),
                ]),
                Toggle::make('record_expense')
                    ->label('Also record it as an expense (paid out)')
                    ->helperText('So it shows in Income vs Expenditure without entering it again.')
                    ->default(true)
                    ->live(),
                Grid::make(2)
                    ->visible(fn (Get $get): bool => (bool) $get('record_expense'))
                    ->schema([
                        Select::make('finance_category_id')
                            ->label('Expense category')
                            ->options(fn (): array => static::expenseCategories())
                            ->default(fn (Get $get): ?int => static::defaultCategory(static::chosen($record, $get)))
                            ->required(fn (Get $get): bool => (bool) $get('record_expense'))
                            ->native(false),
                        Select::make('method')->label('Paid by')->options(FinanceEntry::METHODS)->default('cash')->required(fn (Get $get): bool => (bool) $get('record_expense'))->native(false),
                    ]),
            ])
            ->action(function (?StoreItem $record, array $data, Action $action): void {
                $item = $record ?? StoreItem::where('school_id', auth()->user()?->school_id)->whereKey($data['store_item_id'] ?? 0)->firstOrFail();

                try {
                    app(StoreLedger::class)->receive(
                        $item,
                        (float) $data['quantity'],
                        is_numeric($data['unit_cost'] ?? null) ? (float) $data['unit_cost'] : null,
                        $data['date'],
                        $data['supplier'] ?? null,
                        $data['reference'] ?? null,
                        ! empty($data['record_expense']) ? ['finance_category_id' => (int) $data['finance_category_id'], 'method' => (string) $data['method']] : null,
                    );
                } catch (RuntimeException $e) {
                    Notification::make()->title('Not received')->body($e->getMessage())->danger()->send();
                    $action->halt();
                }

                $item->refresh();
                Notification::make()
                    ->title($item->name.': '.$item->amount().' in the store')
                    ->body(! empty($data['record_expense']) ? 'The expense is recorded in Finance.' : null)
                    ->success()
                    ->send();
            });
    }

    /** Stock given out. */
    public static function issueAction(bool $chooseItem = false): Action
    {
        return Action::make($chooseItem ? 'issueAny' : 'issue')
            ->label('Issue stock')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('warning')
            ->modalHeading(fn (?StoreItem $record): string => $record ? 'Issue '.$record->name : 'Issue stock')
            ->modalDescription(fn (?StoreItem $record): ?string => $record ? 'In the store now: '.$record->amount().'.' : null)
            ->modalSubmitActionLabel('Issue')
            ->schema(fn (?StoreItem $record): array => [
                ...($chooseItem ? [static::itemSelect(inStockOnly: true)] : []),
                Grid::make(2)->schema([
                    TextInput::make('quantity')
                        ->label(fn (Get $get): string => 'Quantity issued ('.(static::chosen($record, $get)->unit ?? 'units').')')
                        ->numeric()->minValue(0.01)->required()
                        ->helperText(fn (Get $get): ?string => ($item = static::chosen($record, $get)) ? 'In the store: '.$item->amount() : null),
                    DatePicker::make('date')->label('Issued on')->default(today())->maxDate(today())->required()->native(false),
                    TextInput::make('issued_to')->label('Issued to')->required()->maxLength(255)->datalist(self::ISSUED_TO)->placeholder('Kitchen, a department or a person'),
                    TextInput::make('reference')->label('Requisition no.')->maxLength(255),
                ]),
            ])
            ->action(function (?StoreItem $record, array $data, Action $action): void {
                $item = $record ?? StoreItem::where('school_id', auth()->user()?->school_id)->whereKey($data['store_item_id'] ?? 0)->firstOrFail();

                try {
                    app(StoreLedger::class)->issue($item, (float) $data['quantity'], (string) $data['issued_to'], $data['date'], $data['reference'] ?? null);
                } catch (RuntimeException $e) {
                    Notification::make()->title('Not issued')->body($e->getMessage())->danger()->send();
                    $action->halt();
                }

                $item->refresh();
                Notification::make()
                    ->title('Issued to '.$data['issued_to'])
                    ->body($item->name.': '.$item->amount().' left'.($item->isLow() ? ', running low.' : '.'))
                    ->{$item->isLow() ? 'warning' : 'success'}()
                    ->send();
            });
    }

    /** The shelf has been counted. */
    public static function countAction(): Action
    {
        return Action::make('count')
            ->label('Count stock')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('gray')
            ->modalHeading(fn (StoreItem $record): string => 'Count '.$record->name)
            ->modalDescription(fn (StoreItem $record): string => 'The records say '.$record->amount().'. After counting what is actually in the store, enter the true amount; the difference is recorded.')
            ->modalWidth('md')
            ->schema([
                TextInput::make('counted')->label('Counted')->numeric()->minValue(0)->required()->default(fn (StoreItem $record): string => $record->quantity),
                DatePicker::make('date')->label('Counted on')->default(today())->maxDate(today())->required()->native(false),
                TextInput::make('notes')->label('Reason for any difference')->maxLength(255)->placeholder('e.g. spoilt by rats, measuring loss'),
            ])
            ->action(function (StoreItem $record, array $data): void {
                $movement = app(StoreLedger::class)->count($record, (float) $data['counted'], $data['date'], $data['notes'] ?? null);

                Notification::make()
                    ->title($movement ? 'Count recorded' : 'The count matches the records')
                    ->body($movement ? 'Difference: '.($movement->quantity > 0 ? '+' : '').$record->amount($movement->quantity).'.' : null)
                    ->success()
                    ->send();
            });
    }

    protected static function itemSelect(bool $inStockOnly = false): Select
    {
        return Select::make('store_item_id')
            ->label('Item')
            ->options(fn (): array => StoreItem::where('school_id', auth()->user()?->school_id)
                ->where('is_active', true)
                ->when($inStockOnly, fn (Builder $q) => $q->where('quantity', '>', 0))
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (StoreItem $item): array => [$item->getKey() => $item->name.' ('.$item->amount().')'])
                ->all())
            ->searchable()
            ->required()
            ->live()
            ->afterStateUpdated(function ($state, callable $set): void {
                $item = StoreItem::where('school_id', auth()->user()?->school_id)->whereKey($state)->first();
                $set('unit_cost', $item?->unit_cost);
                $set('finance_category_id', static::defaultCategory($item));
            });
    }

    protected static function chosen(?StoreItem $record, Get $get): ?StoreItem
    {
        return $record ?? StoreItem::where('school_id', auth()->user()?->school_id)->whereKey($get('store_item_id') ?? 0)->first();
    }

    /** @return array<int, string> */
    protected static function expenseCategories(): array
    {
        $schoolId = (int) auth()->user()?->school_id;
        FinanceCategory::ensureDefaults($schoolId);

        return FinanceCategory::where('school_id', $schoolId)
            ->where('type', 'expense')
            ->whereNull('system_key')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }

    protected static function defaultCategory(?StoreItem $item): ?int
    {
        $name = self::EXPENSE_FOR[$item->category ?? 'other'] ?? 'Other expenses';
        $categories = static::expenseCategories();

        return array_search($name, $categories, true) ?: (array_key_first($categories) ?? null);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStoreItems::route('/'),
        ];
    }
}
