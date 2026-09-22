<?php

namespace App\Filament\App\Resources\SchoolClasses\RelationManagers;

use App\Models\Staff;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The subjects a class takes: compulsory or elective here, and who
 * teaches it. The teacher set here is who may enter the subject's marks.
 */
class SubjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'subjects';

    protected static ?string $title = 'Subjects & teachers';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return \App\Support\Modules::allows('academics');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('is_compulsory')
                ->label('Compulsory in this class')
                ->helperText('Off = an elective: only students who choose it take it.'),
            Select::make('teacher_id')
                ->label('Subject teacher')
                ->options(fn () => static::teacherOptions())
                ->searchable()
                ->placeholder('Not assigned'),
        ]);
    }

    public function table(Table $table): Table
    {
        $teachers = static::teacherOptions();

        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('Subject')->weight('semibold')
                    ->description(fn ($record) => $record->short_name),
                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'core' => 'Core', 'principal' => 'Principal', 'subsidiary' => 'Subsidiary', default => '—',
                    })
                    ->color(fn (string $state) => $state === 'standard' ? 'gray' : 'primary'),
                IconColumn::make('pivot.is_compulsory')
                    ->label('Compulsory')
                    ->boolean(),
                TextColumn::make('pivot.teacher_id')
                    ->label('Teacher')
                    ->formatStateUsing(fn ($state) => $teachers[$state] ?? '—')
                    ->placeholder('Not assigned')
                    ->color(fn ($state) => $state ? null : 'warning'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Add subject')
                    ->preloadRecordSelect()
                    ->multiple()
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query
                        ->where('subjects.school_id', $this->getOwnerRecord()->school_id)
                        ->where('subjects.curriculum', $this->getOwnerRecord()->curriculum())
                        ->where('subjects.is_active', true))
                    ->schema(fn (AttachAction $action) => [
                        $action->getRecordSelect(),
                        Toggle::make('is_compulsory')->label('Compulsory in this class')->default(true),
                        Select::make('teacher_id')->label('Subject teacher')->options(fn () => static::teacherOptions())->searchable(),
                    ]),
            ])
            ->recordActions([
                EditAction::make()->label('Edit'),
                DetachAction::make()->label('Remove'),
            ])
            ->emptyStateHeading('No subjects for this class')
            ->emptyStateDescription('Add them here, or use "Set up Uganda curriculum" under Academics → Subjects.')
            ->paginated(false);
    }

    /** @return array<int, string> */
    protected static function teacherOptions(): array
    {
        return Staff::where('school_id', auth()->user()?->school_id)
            ->where('status', 'active')
            ->where('category', 'teaching')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
