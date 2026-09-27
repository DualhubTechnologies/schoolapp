<?php

namespace App\Filament\App\Resources\SchoolClasses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SchoolClassesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No classes yet')
            ->emptyStateDescription('Add a class for each year group, e.g. P.1 to P.7 or S.1 to S.6. Streams are optional.')
            ->defaultSort('level')
            ->columns([
                TextColumn::make('level')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Class')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('school.name')
                    ->label('School')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sections_count')
                    ->label('Sections')
                    ->counts('sections')
                    ->badge(),
                TextColumn::make('students_count')
                    ->label('Students')
                    ->counts('students')
                    ->badge()
                    ->color('success'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
