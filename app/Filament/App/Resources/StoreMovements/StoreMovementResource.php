<?php

namespace App\Filament\App\Resources\StoreMovements;

use App\Filament\App\Resources\StoreMovements\Pages\ListStoreMovements;
use App\Filament\Concerns\GatedByModule;
use App\Filament\Concerns\PlacedInMenu;
use App\Models\StoreItem;
use App\Models\StoreMovement;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Every quantity into and out of the stores, newest first: the stock card
 * of each item when filtered to it. Read only: lines are written by
 * StoreLedger and never changed, so the history can be trusted.
 */
class StoreMovementResource extends Resource
{
    use GatedByModule;
    use PlacedInMenu;

    protected static ?string $model = StoreMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $modelLabel = 'stock movement';

    protected static ?string $slug = 'stores/movements';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('school_id', auth()->user()?->school_id)->with(['item', 'expense']);
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

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('moved_on')->orderByDesc('id'))
            ->columns([
                TextColumn::make('moved_on')->label('Date')->date('j M Y')->sortable(),
                TextColumn::make('item.name')->label('Item')->weight('semibold')->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => StoreMovement::TYPES[$state] ?? $state)
                    ->color(fn (string $state): string => ['in' => 'success', 'out' => 'warning', 'count' => 'gray'][$state] ?? 'gray'),
                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->formatStateUsing(fn (StoreMovement $record): string => ($record->type === 'in' || ($record->type === 'count' && $record->quantity > 0) ? '+' : '-').$record->item->amount(abs((float) $record->quantity))),
                TextColumn::make('balance_after')->label('Balance')->formatStateUsing(fn (StoreMovement $record): string => $record->item->amount($record->balance_after)),
                TextColumn::make('party')
                    ->label('From / to')
                    ->state(fn (StoreMovement $record): ?string => $record->supplier ?? $record->issued_to ?? $record->notes)
                    ->placeholder('-')
                    ->wrap(),
                TextColumn::make('total_cost')->label('Cost')->placeholder('-')->formatStateUsing(fn ($state): string => 'UGX '.number_format((float) $state)),
                TextColumn::make('reference')->label('Ref.')->placeholder('-')->toggleable(),
                TextColumn::make('expense.voucher_no')->label('Expense')->placeholder('-')->toggleable(),
                TextColumn::make('recorded_by')->label('By')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('store_item_id')
                    ->label('Item')
                    ->options(fn (): array => StoreItem::where('school_id', auth()->user()?->school_id)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                SelectFilter::make('type')->options(StoreMovement::TYPES),
                Filter::make('dates')
                    ->schema([
                        DatePicker::make('from')->native(false),
                        DatePicker::make('until')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $from) => $q->whereDate('moved_on', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $q, $until) => $q->whereDate('moved_on', '<=', $until)))
                    ->indicateUsing(fn (array $data): ?string => ($data['from'] ?? null) || ($data['until'] ?? null) ? 'Dates: '.($data['from'] ?? '…').' to '.($data['until'] ?? '…') : null),
            ])
            ->emptyStateHeading('No stock has moved yet')
            ->emptyStateDescription('Stock received, issued and counted under Store Items shows here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStoreMovements::route('/'),
        ];
    }
}
