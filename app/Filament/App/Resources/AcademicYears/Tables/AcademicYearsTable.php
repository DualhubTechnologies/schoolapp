<?php

namespace App\Filament\App\Resources\AcademicYears\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AcademicYearsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Year')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('school.name')
                    ->label('School')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('terms_count')
                    ->label('Terms')
                    ->counts('terms')
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

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('start_date', 'desc')
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
