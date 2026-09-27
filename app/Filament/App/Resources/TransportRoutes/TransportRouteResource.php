<?php

namespace App\Filament\App\Resources\TransportRoutes;

use App\Filament\App\Resources\TransportRoutes\Pages\ManageTransportRoutes;
use App\Filament\Concerns\GatedByModule;
use App\Models\TransportRoute;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

/**
 * School van routes, each with its fare per term. Learners are put on a
 * route from their student record, and billing adds the fare to their bill.
 */
class TransportRouteResource extends Resource
{
    use GatedByModule;

    protected static ?string $model = TransportRoute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|\UnitEnum|null $navigationGroup = 'Fees';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Transport routes';

    protected static ?string $modelLabel = 'route';

    protected static ?string $pluralModelLabel = 'transport routes';

    protected static ?string $slug = 'transport-routes';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('school_id', auth()->user()?->school_id)
            ->withCount(['students' => fn (Builder $query) => $query->where('status', 'active')]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')
                ->label('Route')
                ->placeholder('e.g. Kakiri')
                ->helperText('The area or road the van covers.')
                ->required()
                ->maxLength(100)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('school_id', auth()->user()?->school_id))
                ->validationMessages(['unique' => 'You already have a route with this name.']),
            TextInput::make('fare')
                ->label('Fare per term (UGX)')
                ->numeric()
                ->minValue(0)
                ->required()
                ->placeholder('e.g. 15000'),
            TextInput::make('one_way_fare')
                ->label('One-way fare per term (UGX)')
                ->numeric()
                ->minValue(0)
                ->placeholder('Leave blank if there is no one-way price')
                ->helperText('For learners who use the van only in the morning or only in the evening.'),
            TextInput::make('capacity')
                ->label('Seats')
                ->numeric()
                ->minValue(1)
                ->placeholder('e.g. 30'),
            Section::make('Van and driver')
                ->description('Optional. Shown on the route list for the gate and the driver.')
                ->columns(3)
                ->columnSpanFull()
                ->compact()
                ->schema([
                    TextInput::make('vehicle')->label('Number plate')->placeholder('e.g. UAX 123B')->maxLength(50),
                    TextInput::make('driver_name')->label('Driver')->maxLength(100),
                    TextInput::make('driver_phone')->label('Driver\'s phone')->tel()->placeholder('07XX XXX XXX')->maxLength(30),
                ]),
            Toggle::make('is_active')
                ->label('In use')
                ->helperText('Routes not in use are not billed and cannot be chosen for learners.')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Route')
                    ->weight('semibold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('fare')
                    ->label('Per term (UGX)')
                    ->numeric()
                    ->sortable()
                    ->description(fn (TransportRoute $record): ?string => $record->one_way_fare !== null
                        ? 'One way: '.number_format((float) $record->one_way_fare)
                        : null),
                TextColumn::make('students_count')
                    ->label('Learners')
                    ->badge()
                    ->color(fn (TransportRoute $record): string => $record->capacity && $record->students_count >= $record->capacity ? 'danger' : 'info')
                    ->formatStateUsing(fn (TransportRoute $record): string => $record->capacity
                        ? "{$record->students_count} / {$record->capacity}"
                        : (string) $record->students_count),
                TextColumn::make('driver_name')
                    ->label('Van & driver')
                    ->placeholder('—')
                    ->description(fn (TransportRoute $record): ?string => collect([$record->vehicle, $record->driver_phone])->filter()->join(' · ') ?: null),
                IconColumn::make('is_active')
                    ->label('In use')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Learners on this route will be set to "brought by parent". Past transport charges stay on their accounts.'),
            ])
            ->emptyStateHeading('No routes yet')
            ->emptyStateDescription('Add each van route with its fare per term, e.g. Kakiri — 15,000.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTransportRoutes::route('/'),
        ];
    }
}
