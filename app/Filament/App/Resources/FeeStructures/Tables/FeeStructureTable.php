<?php

namespace App\Filament\App\Resources\FeeStructures\Tables;

use App\Models\FeeStructure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FeeStructureTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Fee')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('schoolClass.name')
                    ->label('Class')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('frequency')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => FeeStructure::FREQUENCIES[$state] ?? $state)
                    ->color(fn (?string $state): string => match ($state) {
                        'per_term' => 'success',
                        'once' => 'warning',
                        'on_demand' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('applies_to')
                    ->label('Applies to')
                    ->formatStateUsing(fn (?string $state) => FeeStructure::APPLIES_TO[$state] ?? $state)
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'new_only' ? 'warning' : 'gray'),
                TextColumn::make('amount')
                    ->money('UGX')
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('term')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('academic_year')
                    ->label('Year')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('school_class_id')
                    ->label('Class')
                    ->relationship('schoolClass', 'name')
                    ->preload(),
                SelectFilter::make('frequency')
                    ->options(FeeStructure::FREQUENCIES),
                SelectFilter::make('applies_to')
                    ->options(FeeStructure::APPLIES_TO),
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
