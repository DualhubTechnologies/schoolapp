<?php

namespace App\Filament\App\Resources\GradingScales;

use App\Filament\App\Resources\GradingScales\Pages\ManageGradingScales;
use App\Models\GradingScale;
use App\Support\AcademicAccess;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Mark → grade tables. Each school sets its own cut-offs; the defaults
 * follow the usual Ugandan scales (D1–F9, A–E, A–F).
 */
class GradingScaleResource extends Resource
{
    protected static ?string $model = GradingScale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|\UnitEnum|null $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Grading Scales';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('school_id', auth()->user()?->school_id)
            ->with('bands');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(150),
            Repeater::make('bands')
                ->relationship('bands', fn (Builder $q) => $q->reorder()->orderByDesc('min_score'))
                ->label('Grades (highest first)')
                ->schema([
                    TextInput::make('grade')->required()->maxLength(10),
                    TextInput::make('min_score')->label('From %')->numeric()->minValue(0)->maxValue(100)->required(),
                    TextInput::make('max_score')->label('To %')->numeric()->minValue(0)->maxValue(100)->required(),
                    TextInput::make('value')
                        ->label('Value / points')
                        ->numeric()
                        ->required()
                        ->helperText('Aggregate number (primary) or points (A-Level).'),
                    TextInput::make('descriptor')->maxLength(100),
                ])
                ->columns(5)
                ->reorderable(false)
                ->addActionLabel('Add grade')
                ->minItems(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('curriculum')
            ->columns([
                TextColumn::make('name')->weight('semibold'),
                TextColumn::make('bands')
                    ->label('Grades')
                    ->state(fn (GradingScale $record) => $record->bands
                        ->map(fn ($b) => $b->grade . ' ' . (float) $b->min_score . '–' . (float) $b->max_score)
                        ->implode('  ·  '))
                    ->wrap(),
            ])
            ->recordActions([EditAction::make()->modalWidth('5xl')])
            ->emptyStateHeading('No grading scales yet')
            ->emptyStateDescription('They are created when you set up the Uganda curriculum under Academics → Subjects.')
            ->paginated(false);
    }

    public static function canViewAny(): bool
    {
        return AcademicAccess::manages();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ManageGradingScales::route('/')];
    }
}
