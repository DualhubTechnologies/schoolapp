<?php

namespace App\Filament\App\Resources\Assessments;

use App\Filament\App\Resources\Assessments\Pages\ManageAssessments;
use App\Filament\Concerns\GatedByModule;
use App\Filament\Pages\EnterMarks;
use App\Models\Assessment;
use App\Models\Term;
use App\Support\AcademicAccess;
use App\Support\SchoolType;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The term's exams and continuous assessments. Each one's weight is its
 * share of the term result.
 */
class AssessmentResource extends Resource
{
    use GatedByModule;

    protected static ?string $model = Assessment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Exams & Results';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Exams';

    protected static ?string $modelLabel = 'exam';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('school_id', auth()->user()?->school_id)
            ->with('term.academicYear')
            ->withCount('marks');
    }

    public static function form(Schema $schema): Schema
    {
        $applyDefaults = function (Get $get, Set $set): void {
            $type = $get('type');
            $curriculum = $get('curriculum') ?: 'primary';

            if (! $type) {
                return;
            }

            $set('weight', config("academics.default_weights.{$curriculum}.{$type}", 0));
            $set('name', config('academics.assessment_types')[$type] ?? '');
            $set('max_score', match (true) {
                $type === 'ca' && $curriculum === 'o_level' => 3,
                $type === 'project' => 10,
                default => 100,
            });
        };

        return $schema->columns(2)->components([
            Select::make('term_id')
                ->label('Term')
                ->options(fn () => Term::where('school_id', auth()->user()?->school_id)
                    ->with('academicYear')
                    ->get()
                    ->sortByDesc(fn (Term $t) => $t->sortKey())
                    ->mapWithKeys(fn (Term $t) => [$t->id => $t->label()])
                    ->all())
                ->default(fn () => Term::current()?->getKey())
                ->required()
                ->native(false),
            Select::make('curriculum')
                ->label('For')
                ->options(fn () => SchoolType::curricula())
                ->placeholder('All classes')
                ->native(false)
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set, ?Assessment $record) use ($applyDefaults): void {
                    // e.g. a Mid-Term picked before switching to O-Level, which has none.
                    if (! array_key_exists((string) $get('type'), self::typeOptions($get('curriculum'), $record))) {
                        $set('type', null);
                    }

                    $applyDefaults($get, $set);
                }),
            Select::make('type')
                ->options(fn (Get $get, ?Assessment $record): array => self::typeOptions($get('curriculum'), $record))
                ->required()
                ->native(false)
                ->live()
                ->afterStateUpdated($applyDefaults),
            TextInput::make('name')
                ->required()
                ->maxLength(100)
                ->placeholder('e.g. Mid-Term Examination'),
            TextInput::make('max_score')
                ->label('Marked out of')
                ->numeric()
                ->minValue(1)
                ->default(100)
                ->required()
                ->helperText(fn (Get $get): string => match ($get('type')) {
                    'eot' => 'Mark out of whatever the paper is set out of, e.g. 100 or 80. Its weight below decides how much it counts.',
                    'project' => 'Project work is marked out of 10.',
                    'ca' => 'Activities of Integration are usually scored out of 3, but mark out of whatever suits the school, e.g. 3, 10 or 20. Its weight below decides how much it counts.',
                    default => 'New curriculum Activities of Integration are usually scored out of 3.',
                }),
            TextInput::make('weight')
                ->hintIcon('heroicon-m-question-mark-circle', tooltip: "How much this exam counts in the term's final mark, e.g. BOT 20%, MOT 30%, EOT 50%.")
                ->label('Weight in term result')
                ->numeric()
                ->suffix('%')
                ->minValue(0)
                ->maxValue(100)
                ->default(0)
                ->required()
                // Project work is reported on its own and carries no weight.
                ->hidden(fn (Get $get): bool => $get('type') === 'project')
                ->helperText(fn (Get $get): string => $get('type') === 'ca' && $get('curriculum') === 'o_level'
                    ? 'All of the term\'s Activities of Integration are averaged, and together count this much.'
                    : 'e.g. CA 20% + End of Term 80%. If none of a student\'s exams has a weight, they count equally.'),
            DatePicker::make('held_on')->label('Date')->native(false)->displayFormat('j M Y'),
            TextInput::make('sort_order')->label('Order on report card')->numeric()->default(0),
        ]);
    }

    /**
     * The exam types a school can pick for a curriculum. Topic assessment is
     * created by Assess Topics, never by hand, and O-Level follows the new
     * curriculum (CA, End of Term, Project work). An existing exam always
     * keeps its own type.
     *
     * @return array<string, string>
     */
    public static function typeOptions(?string $curriculum, ?Assessment $record = null): array
    {
        // "All classes" in a school that only runs O-Level is O-Level.
        $keys = SchoolType::keys();
        $curriculum ??= count($keys) === 1 ? $keys[0] : null;
        $allowed = $curriculum ? config("academics.curriculum_assessment_types.{$curriculum}") : null;

        return array_filter(
            (array) config('academics.assessment_types'),
            fn (string $type): bool => $type === $record?->type
                || ($type !== 'topics' && ($allowed === null || in_array($type, (array) $allowed, true))),
            ARRAY_FILTER_USE_KEY,
        );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('term_id')->orderBy('sort_order')->orderBy('held_on'))
            ->columns([
                TextColumn::make('name')->weight('semibold')->searchable()
                    ->description(fn (Assessment $a) => $a->typeLabel()),
                TextColumn::make('term.name')->label('Term')
                    ->description(fn (Assessment $a) => $a->term?->academicYear?->name),
                TextColumn::make('curriculum')->label('For')
                    ->formatStateUsing(fn (?string $state) => config('academics.curricula')[$state] ?? 'All classes')
                    ->placeholder('All classes'),
                TextColumn::make('max_score')->label('Out of')->numeric()->alignCenter(),
                TextColumn::make('weight')->label('Weight')->suffix('%')->numeric()->alignCenter(),
                TextColumn::make('marks_count')->label('Marks entered')->numeric()->alignCenter(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'locked' ? 'Locked' : 'Open')
                    ->color(fn (string $state) => $state === 'locked' ? 'gray' : 'success'),
            ])
            ->filters([
                SelectFilter::make('term_id')
                    ->label('Term')
                    ->options(fn () => Term::where('school_id', auth()->user()?->school_id)->with('academicYear')->get()->mapWithKeys(fn (Term $t) => [$t->id => $t->label()])->all())
                    ->default(fn () => Term::current()?->getKey()),
                SelectFilter::make('type')->options(config('academics.assessment_types')),
            ])
            ->recordActions([
                Action::make('marks')
                    ->label('Enter marks')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Assessment $a) => EnterMarks::getUrl(['assessment' => $a->getKey()])),
                ActionGroup::make([
                    Action::make('lock')
                        ->label(fn (Assessment $a) => $a->isLocked() ? 'Reopen for marks' : 'Lock marks')
                        ->icon(fn (Assessment $a) => $a->isLocked() ? 'heroicon-o-lock-open' : 'heroicon-o-lock-closed')
                        ->requiresConfirmation()
                        ->modalDescription(fn (Assessment $a) => $a->isLocked()
                            ? 'Teachers will be able to change marks again.'
                            : 'Marks can no longer be changed until you reopen the exam.')
                        ->action(fn (Assessment $a) => $a->update(['status' => $a->isLocked() ? 'open' : 'locked'])),
                    EditAction::make(),
                    DeleteAction::make()
                        ->modalDescription('Deletes the exam and every mark entered for it.'),
                ])->visible(fn () => AcademicAccess::manages()),
            ])
            ->emptyStateHeading('No exams for this term')
            ->emptyStateDescription('Add the term\'s exams (e.g. Beginning, Mid and End of Term), then enter marks.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }

    public static function canViewAny(): bool
    {
        return AcademicAccess::teaches();
    }

    public static function canCreate(): bool
    {
        return AcademicAccess::manages();
    }

    public static function getPages(): array
    {
        return ['index' => ManageAssessments::route('/')];
    }
}
