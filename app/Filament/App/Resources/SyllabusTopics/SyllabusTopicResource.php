<?php

namespace App\Filament\App\Resources\SyllabusTopics;

use App\Filament\App\Resources\SyllabusTopics\Pages\ManageSyllabusTopics;
use App\Filament\Concerns\GatedByModule;
use App\Models\Subject;
use App\Models\SyllabusTopic;
use App\Support\AcademicAccess;
use App\Support\Modules;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The NCDC syllabus topics of each O-Level subject, per class. Teachers
 * assess learners on them under Assess Topics.
 */
class SyllabusTopicResource extends Resource
{
    use GatedByModule;

    protected static ?string $model = SyllabusTopic::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|\UnitEnum|null $navigationGroup = 'Exams & Results';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Syllabus topics';

    protected static ?string $recordTitleAttribute = 'name';

    public const CLASSES = [1 => 'S.1', 2 => 'S.2', 3 => 'S.3', 4 => 'S.4'];

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('school_id', auth()->user()?->school_id)->with('subject');
    }

    /**
     * @return array<int, string>
     */
    public static function subjectOptions(): array
    {
        $options = [];

        foreach (Subject::where('school_id', auth()->user()?->school_id)->where('curriculum', 'o_level')->orderBy('sort_order')->orderBy('name')->get() as $subject) {
            $options[(int) $subject->id] = (string) $subject->name;
        }

        return $options;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('subject_id')
                ->label('Subject')
                ->options(fn (): array => self::subjectOptions())
                ->required()
                ->native(false),
            Select::make('class_number')
                ->label('Class')
                ->options(self::CLASSES)
                ->required()
                ->native(false),
            TextInput::make('code')
                ->label('Topic no.')
                ->placeholder('e.g. T5')
                ->maxLength(20),
            TextInput::make('name')
                ->label('Topic')
                ->required()
                ->maxLength(255),
            TextInput::make('sort_order')
                ->label('Order')
                ->numeric()
                ->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->groups([
                Group::make('subject.name')->label('Subject'),
            ])
            ->defaultGroup('subject.name')
            ->defaultSort(fn (Builder $query) => $query->orderBy('class_number')->orderBy('sort_order')->orderBy('id'))
            ->columns([
                TextColumn::make('class_number')
                    ->label('Class')
                    ->formatStateUsing(fn (int $state): string => self::CLASSES[$state] ?? "S.{$state}")
                    ->badge()
                    ->color('gray'),
                TextColumn::make('code')->label('No.'),
                TextColumn::make('name')->label('Topic')->searchable()->wrap(),
            ])
            ->filters([
                SelectFilter::make('subject_id')->label('Subject')->options(fn (): array => self::subjectOptions()),
                SelectFilter::make('class_number')->label('Class')->options(self::CLASSES),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Learners\' levels for this topic are deleted with it.'),
            ])
            ->emptyStateHeading('No syllabus topics yet')
            ->emptyStateDescription('Add each subject\'s topics from its NCDC syllabus, one class at a time. "Add several topics" takes a pasted list.')
            ->emptyStateIcon('heroicon-o-list-bullet')
            ->paginated([50, 100, 'all']);
    }

    public static function canViewAny(): bool
    {
        return Modules::allows('exams') && AcademicAccess::teaches();
    }

    public static function canCreate(): bool
    {
        return AcademicAccess::teaches();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSyllabusTopics::route('/'),
        ];
    }
}
