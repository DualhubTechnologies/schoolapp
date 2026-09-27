<?php

namespace App\Filament\App\Resources\Sections\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No streams')
            ->emptyStateDescription('Streams are optional. Add them only if you split a class, e.g. S.1 East and S.1 West.')
            ->columns([
                TextColumn::make('schoolClass.name')
                    ->label('Class')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Section')
                    ->searchable(),
                TextColumn::make('classTeacher.name')
                    ->label('Class teacher')
                    ->placeholder('Unassigned')
                    ->searchable(),
                TextColumn::make('students_count')
                    ->label('Students')
                    ->counts('students')
                    ->badge()
                    ->color('success'),
                TextColumn::make('capacity')
                    ->placeholder('No limit'),
                TextColumn::make('school.name')
                    ->label('School')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('school_class_id')
                    ->label('Class')
                    ->relationship('schoolClass', 'name')
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->paginationPageOptions([5, 10, 25, 50])
            ->defaultPaginationPageOption(5);
    }
}
