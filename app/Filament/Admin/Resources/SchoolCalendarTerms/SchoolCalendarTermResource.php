<?php

namespace App\Filament\Admin\Resources\SchoolCalendarTerms;

use App\Filament\Admin\Resources\SchoolCalendarTerms\Pages\ManageSchoolCalendarTerms;
use App\Models\SchoolCalendarTerm;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/**
 * Uganda's school terms, which paid SchoolHub terms follow: a term runs
 * to the day before the next one opens. Keep the coming year here once
 * the Ministry publishes it; dates already paid for do not move.
 */
class SchoolCalendarTermResource extends Resource
{
    protected static ?string $model = SchoolCalendarTerm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Platform Management';

    protected static ?string $navigationLabel = 'School calendar';

    protected static ?string $modelLabel = 'school term';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'school-calendar';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('year')->numeric()->minValue(2020)->maxValue(2100)->default(now()->year + 1)->required()->live(),
                Select::make('term')
                    ->options([1 => 'Term 1', 2 => 'Term 2', 3 => 'Term 3'])
                    ->required()
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('year', $get('year'))),
                DatePicker::make('opens_on')->label('Opens')->native(false)->displayFormat('j M Y')->required(),
                DatePicker::make('closes_on')->label('Closes')->native(false)->displayFormat('j M Y')->after('opens_on')->required(),
                Toggle::make('is_estimate')->label('Estimate (not yet published by the Ministry)')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('opens_on')
            ->columns([
                TextColumn::make('label')->label('Term')->state(fn (SchoolCalendarTerm $record): string => $record->label())->weight('semibold'),
                TextColumn::make('opens_on')->label('Opens')->date('D j M Y'),
                TextColumn::make('closes_on')->label('Closes')->date('D j M Y'),
                TextColumn::make('is_estimate')
                    ->label('Dates')
                    ->badge()
                    ->state(fn (SchoolCalendarTerm $record): string => $record->is_estimate ? 'Estimate' : 'Published')
                    ->color(fn (SchoolCalendarTerm $record): string => $record->is_estimate ? 'warning' : 'success'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('No school terms yet')
            ->emptyStateDescription('Without them, a paid term is counted as 4 months.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSchoolCalendarTerms::route('/'),
        ];
    }
}
