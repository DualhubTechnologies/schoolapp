<?php

namespace App\Filament\App\Resources\Assets;

use App\Filament\App\Resources\Assets\Pages\ManageAssets;
use App\Filament\Concerns\GatedByModule;
use App\Filament\Concerns\PlacedInMenu;
use App\Models\Asset;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * The asset register: what the school owns, its tag number, where it is,
 * who looks after it and the state it is in. Lost or disposed-of items
 * stay on the register, marked, so the record is complete for audits.
 */
class AssetResource extends Resource
{
    use GatedByModule;
    use PlacedInMenu;

    protected static ?string $model = Asset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $modelLabel = 'asset';

    protected static ?string $slug = 'stores/assets';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('school_id', auth()->user()?->school_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(255)->placeholder('e.g. Student desk, HP laptop, Generator')->columnSpanFull(),
            Select::make('category')->options(Asset::CATEGORIES)->default('furniture')->required()->native(false),
            TextInput::make('quantity')->label('How many')->numeric()->integer()->minValue(1)->default(1)->required()
                ->helperText('Several identical items in one place can share one tag.'),
            TextInput::make('location')->label('Where it is')->maxLength(255)->datalist(fn (): array => static::locations())->placeholder('e.g. S.1 East, Computer lab, Staff room'),
            TextInput::make('assigned_to')->label('Looked after by')->maxLength(255)->placeholder('A person or department'),
            Select::make('condition')->options(Asset::CONDITIONS)->default('good')->required()->native(false)->live(),
            DatePicker::make('disposed_on')
                ->label(fn (Get $get): string => $get('condition') === 'lost' ? 'Lost on' : 'Disposed of on')
                ->visible(fn (Get $get): bool => in_array($get('condition'), ['lost', 'disposed'], true))
                ->maxDate(today())
                ->native(false),
            TextInput::make('serial_no')->label('Serial / model no.')->maxLength(255),
            DatePicker::make('acquired_on')->label('Bought or received on')->maxDate(today())->native(false),
            TextInput::make('cost')->label('Cost (UGX, all together)')->numeric()->minValue(0),
            TextInput::make('supplier')->maxLength(255)->placeholder('Bought from, or donated by'),
            Textarea::make('notes')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('tag_seq')
            ->description(function (): string {
                $assets = Asset::where('school_id', auth()->user()?->school_id)->inUse();
                $count = (clone $assets)->sum('quantity');
                $value = (clone $assets)->sum('cost');
                $repair = Asset::where('school_id', auth()->user()?->school_id)->whereIn('condition', ['poor', 'broken'])->count();

                return number_format((int) $count).' items in use, bought for UGX '.number_format((float) $value).($repair ? " · {$repair} ".str('record')->plural($repair).' need repair' : '').'.';
            })
            ->columns([
                TextColumn::make('tag_no')->label('Tag')->weight('semibold')->searchable()->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('tag_seq', $direction === 'desc' ? 'desc' : 'asc')),
                TextColumn::make('name')->searchable()->sortable()
                    ->description(fn (Asset $record): string => (Asset::CATEGORIES[$record->category] ?? $record->category).($record->serial_no ? ' · '.$record->serial_no : '')),
                TextColumn::make('quantity')->label('Qty')->numeric()->alignCenter(),
                TextColumn::make('location')->label('Where')->placeholder('-')->searchable()->sortable(),
                TextColumn::make('condition')->badge()
                    ->formatStateUsing(fn (string $state): string => Asset::CONDITIONS[$state] ?? $state)
                    ->color(fn (Asset $record): string => $record->conditionColor()),
                TextColumn::make('assigned_to')->label('Looked after by')->placeholder('-')->toggleable(),
                TextColumn::make('cost')->placeholder('-')->formatStateUsing(fn ($state): string => 'UGX '.number_format((float) $state))->sortable()->toggleable(),
                TextColumn::make('acquired_on')->label('Acquired')->date('M Y')->placeholder('-')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')->options(Asset::CATEGORIES),
                SelectFilter::make('location')->label('Where')->options(fn (): array => array_combine(static::locations(), static::locations()))->searchable(),
                SelectFilter::make('condition')->options(Asset::CONDITIONS)->multiple(),
                TernaryFilter::make('in_use')
                    ->label('Still with the school')
                    ->placeholder('Everything on the register')
                    ->trueLabel('In use')
                    ->falseLabel('Lost or disposed of')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotIn('condition', ['lost', 'disposed']),
                        false: fn (Builder $query) => $query->whereIn('condition', ['lost', 'disposed']),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->modalDescription('Delete only an asset entered by mistake. One that was lost or disposed of should stay on the register: change its condition instead.'),
            ])
            ->toolbarActions([
                BulkAction::make('move')
                    ->label('Move to…')
                    ->icon('heroicon-o-map-pin')
                    ->schema([TextInput::make('location')->label('New place')->required()->datalist(fn (): array => static::locations())])
                    ->action(function (Collection $records, array $data): void {
                        $records->each(fn (Model $asset) => $asset->update(['location' => $data['location']]));
                        Notification::make()->title($records->count().' moved to '.$data['location'])->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('condition')
                    ->label('Set condition…')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->schema([Select::make('condition')->options(Asset::CONDITIONS)->required()->native(false)])
                    ->action(function (Collection $records, array $data): void {
                        $records->each(fn (Model $asset) => $asset->update([
                            'condition' => $data['condition'],
                            'disposed_on' => in_array($data['condition'], ['lost', 'disposed'], true) ? ($asset->getAttribute('disposed_on') ?? today()) : null,
                        ]));
                        Notification::make()->title($records->count().' updated')->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading('The asset register is empty')
            ->emptyStateDescription('Add what the school owns (desks, beds, computers, the generator) to know where everything is and what state it is in. Each gets a tag number to mark on it.');
    }

    /** @return list<string> */
    public static function locations(): array
    {
        return array_values(Asset::where('school_id', auth()->user()?->school_id)->whereNotNull('location')->distinct()->orderBy('location')->pluck('location')->map(fn ($location): string => (string) $location)->all());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAssets::route('/'),
        ];
    }
}
