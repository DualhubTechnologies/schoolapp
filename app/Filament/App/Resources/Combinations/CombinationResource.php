<?php

namespace App\Filament\App\Resources\Combinations;

use App\Filament\App\Resources\Combinations\Pages\ManageCombinations;
use App\Filament\Concerns\GatedByModule;
use App\Models\Combination;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Support\Modules;
use App\Support\SchoolType;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

/**
 * A-Level subject combinations. A student's combination decides which
 * three principal subjects count towards their points.
 */
class CombinationResource extends Resource
{
    use GatedByModule;

    protected static ?string $model = Combination::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquaresPlus;

    protected static string|\UnitEnum|null $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'A-Level Combinations';

    protected static ?string $modelLabel = 'combination';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('school_id', auth()->user()?->school_id)
            ->with(['subjects', 'subsidiary'])
            ->withCount('students');
    }

    public static function form(Schema $schema): Schema
    {
        $subjects = fn (string $category) => Subject::where('school_id', auth()->user()?->school_id)
            ->where('curriculum', 'a_level')
            ->where('category', $category)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id');

        return $schema->columns(2)->components([
            TextInput::make('name')
                ->label('Code')
                ->placeholder('e.g. PCM')
                ->required()
                ->maxLength(20)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('school_id', auth()->user()?->school_id))
                ->validationMessages(['unique' => 'You already have this combination.']),
            TextInput::make('description')
                ->placeholder('e.g. Physics, Chemistry, Mathematics'),
            Select::make('subjects')
                ->label('Principal subjects')
                ->relationship('subjects', 'name', fn (Builder $q) => $q
                    ->where('subjects.school_id', auth()->user()?->school_id)
                    ->where('subjects.curriculum', 'a_level')
                    ->where('subjects.category', 'principal'))
                ->multiple()
                ->preload()
                ->minItems(3)
                ->maxItems(3)
                ->required()
                ->helperText('Exactly three.')
                ->columnSpanFull(),
            Select::make('subsidiary_subject_id')
                ->label('Subsidiary subject')
                ->options(fn () => $subjects('subsidiary')->reject(fn ($name) => stripos($name, 'general paper') !== false))
                ->helperText('General Paper is taken by everyone as well. Usually Sub-ICT with principal Mathematics, otherwise Sub-Mathematics.')
                ->native(false),
            Toggle::make('is_active')->label('Offered')->default(true)->inline(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Code')->weight('bold')->searchable(),
                TextColumn::make('subjects.name')->label('Principal subjects')->listWithLineBreaks(false)->separator(', '),
                TextColumn::make('subsidiary.name')->label('Subsidiary')->placeholder('—'),
                TextColumn::make('students_count')->label('Students')->alignCenter(),
                IconColumn::make('is_active')->label('Offered')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('No combinations yet')
            ->emptyStateDescription('Set up the Uganda curriculum under Academics → Subjects to add the common combinations.')
            ->paginated(false);
    }

    public static function canViewAny(): bool
    {
        return Modules::allows('academics') && SchoolType::allows('a_level');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny()
            && SchoolClass::where('school_id', auth()->user()?->school_id)
                ->whereHas('classLevel', fn ($q) => $q->where('curriculum', 'a_level'))
                ->exists();
    }

    public static function getPages(): array
    {
        return ['index' => ManageCombinations::route('/')];
    }
}
