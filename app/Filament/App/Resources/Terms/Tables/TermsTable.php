<?php

namespace App\Filament\App\Resources\Terms\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TermsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No terms yet')
            ->emptyStateDescription("Add this year's terms and mark the one you are in as current. Fees and marks are recorded against it.")
            ->columns([
                TextColumn::make('academicYear.name')
                    ->label('Year')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Term')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sequence')
                    ->label('Order')
                    ->sortable(),

                TextColumn::make('start_date')
                    ->label('Starts')
                    ->date()
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Ends')
                    ->date()
                    ->placeholder('—')
                    ->sortable(),

                IconColumn::make('is_current')
                    ->label('Current')
                    ->boolean(),

                TextColumn::make('school.name')
                    ->label('School')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sequence')
            ->filters([
                SelectFilter::make('academic_year_id')
                    ->label('Academic year')
                    ->relationship('academicYear', 'name')
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
            ->defaultPaginationPageOption(10);
    }
}
