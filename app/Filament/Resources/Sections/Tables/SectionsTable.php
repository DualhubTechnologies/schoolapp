<?php

namespace App\Filament\Resources\Sections\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SectionTable
{
    public static function configure(Table $table): Table
    {
        return $table
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
            ]);
    }
}