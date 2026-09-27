<?php

namespace App\Filament\App\Resources\Houses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HousesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('House')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('school.name')
                    ->label('School')
                    ->searchable(),

                // Useful at a glance — shows how evenly students are
                // spread across houses.
                TextColumn::make('students_count')
                    ->label('Students')
                    ->counts('students')
                    ->sortable(),

                TextColumn::make('capacity_status')
                    ->label('Capacity')
                    ->badge()
                    ->state(function ($record): string {
                        if (is_null($record->capacity)) {
                            return 'Unlimited';
                        }

                        $count = $record->students()->count();

                        return $count >= $record->capacity
                            ? "Full ({$count}/{$record->capacity})"
                            : "Available ({$count}/{$record->capacity})";
                    })
                    ->color(fn ($record): string => match (true) {
                        is_null($record->capacity) => 'gray',
                        $record->students()->count() >= $record->capacity => 'danger',
                        default => 'success',
                    }),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                //
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
            ->defaultPaginationPageOption(10);
    }
}
