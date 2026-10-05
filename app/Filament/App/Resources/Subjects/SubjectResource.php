<?php

namespace App\Filament\App\Resources\Subjects;

use App\Filament\App\Resources\Subjects\Pages\ManageSubjects;
use App\Filament\Concerns\GatedByModule;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

/**
 * The subjects the school teaches, per curriculum. Which class takes
 * which subject (and who teaches it) is set on each class.
 */
class SubjectResource extends Resource
{
    use GatedByModule;

    protected static ?string $model = Subject::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 8;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('school_id', auth()->user()?->school_id)
            ->whereIn('curriculum', SchoolType::keys())
            ->withCount('classes');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('curriculum')
                ->options(fn () => SchoolType::curricula())
                ->required()
                ->native(false)
                ->live(),
            TextInput::make('name')
                ->required()
                ->maxLength(100)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                    ->where('school_id', auth()->user()?->school_id)
                    ->where('curriculum', $get('curriculum')))
                ->validationMessages(['unique' => 'This curriculum already has a subject with that name.']),
            TextInput::make('short_name')
                ->label('Short name')
                ->placeholder('e.g. ENG, MTC')
                ->helperText('Used as the column heading on broadsheets.')
                ->maxLength(20),
            TextInput::make('code')
                ->label('UNEB code')
                ->maxLength(20),
            Select::make('category')
                ->options(fn (Get $get) => match ($get('curriculum')) {
                    'a_level' => ['principal' => Subject::CATEGORIES['principal'], 'subsidiary' => Subject::CATEGORIES['subsidiary']],
                    'primary' => ['core' => Subject::CATEGORIES['core'], 'standard' => Subject::CATEGORIES['standard']],
                    default => ['standard' => Subject::CATEGORIES['standard']],
                })
                ->default('standard')
                ->required()
                ->native(false)
                ->helperText('Primary: the four core subjects make the aggregate. A-Level: principal subjects earn 6–0 points, subsidiaries 1.'),
            Select::make('papers')
                ->label('Papers')
                ->options([1 => 'One paper', 2 => 'Paper 1 and 2', 3 => 'Papers 1–3', 4 => 'Papers 1–4'])
                ->default(1)
                ->required()
                ->native(false)
                ->helperText('Subjects sat as several papers (e.g. A-Level Biology P1, P2) get a mark per paper; the papers are averaged.'),
            TextInput::make('sort_order')
                ->label('Order')
                ->numeric()
                ->default(0),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->groups([Group::make('curriculum')->label('Curriculum')->getTitleFromRecordUsing(fn (Subject $s) => config('academics.curricula')[$s->curriculum] ?? $s->curriculum)])
            ->defaultGroup('curriculum')
            ->defaultSort(fn (Builder $query) => $query->orderBy('sort_order')->orderBy('name'))
            ->columns([
                TextColumn::make('name')->searchable()->weight('semibold')
                    ->description(fn (Subject $s) => $s->code ? 'UNEB '.$s->code : null),
                TextColumn::make('short_name')->label('Short')->badge()->color('gray'),
                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'core' => 'Core', 'principal' => 'Principal', 'subsidiary' => 'Subsidiary', default => '—',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'core', 'principal' => 'primary', 'subsidiary' => 'info', default => 'gray',
                    }),
                TextColumn::make('classes_count')->label('Classes')->alignCenter(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('curriculum')->options(fn () => SchoolType::curricula()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Deleting a subject also deletes all marks entered for it. Consider switching it off (Active) instead.'),
            ])
            ->emptyStateHeading('No subjects yet')
            ->emptyStateDescription('Click "Set up Uganda curriculum" to add the standard subjects for your classes.')
            ->emptyStateIcon('heroicon-o-book-open')
            ->paginated(false);
    }

    public static function canViewAny(): bool
    {
        return Modules::allows('academics');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSubjects::route('/'),
        ];
    }
}
