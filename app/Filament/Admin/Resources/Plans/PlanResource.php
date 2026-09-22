<?php

namespace App\Filament\Admin\Resources\Plans;

use App\Filament\Admin\Resources\Plans\Pages\ManagePlans;
use App\Models\Plan;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Subscription tiers. Every tier has every feature; they differ by the
 * number of active students and staff logins.
 */
class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Platform Management';

    protected static ?string $navigationLabel = 'Plans';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('name')->required()->maxLength(60),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(60)
                    ->unique(ignoreRecord: true)
                    ->helperText('Short code, e.g. "standard".'),
                TextInput::make('max_students')
                    ->label('Active students')
                    ->numeric()
                    ->minValue(1)
                    ->placeholder('Unlimited')
                    ->helperText('Leave blank for unlimited.'),
                TextInput::make('max_users')
                    ->label('Staff logins')
                    ->numeric()
                    ->minValue(1)
                    ->placeholder('Unlimited')
                    ->helperText('Parents and students do not count.'),
                TextInput::make('price_per_term')
                    ->label('Price per term (UGX)')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                TextInput::make('price_per_year')
                    ->label('Price per year (UGX)')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->helperText('Usually about 10% less than three terms.'),
                TextInput::make('description')->maxLength(255)->columnSpanFull(),
                TextInput::make('sort_order')->numeric()->default(0),
                Toggle::make('is_active')->label('Offered to schools')->default(true)->inline(false),
                Toggle::make('is_trial')->label('This is the free trial plan')->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')->weight('bold')->description(fn (Plan $record) => $record->description),
                TextColumn::make('max_students')->label('Students')->formatStateUsing(fn ($state) => Plan::limitLabel($state))->placeholder('Unlimited'),
                TextColumn::make('max_users')->label('Logins')->formatStateUsing(fn ($state) => Plan::limitLabel($state))->placeholder('Unlimited'),
                TextColumn::make('price_per_term')->label('Per term')->numeric()->prefix('UGX '),
                TextColumn::make('price_per_year')->label('Per year')->numeric()->prefix('UGX '),
                TextColumn::make('schools')
                    ->label('Schools on it')
                    ->state(fn (Plan $record) => $record->subscriptions()
                        ->where('is_cancelled', false)
                        ->where('starts_on', '<=', today())
                        ->where('ends_on', '>=', today())
                        ->distinct('school_id')
                        ->count('school_id')),
                IconColumn::make('is_trial')->label('Trial')->boolean(),
                IconColumn::make('is_active')->label('Offered')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (Plan $record) => $record->subscriptions()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePlans::route('/'),
        ];
    }
}
