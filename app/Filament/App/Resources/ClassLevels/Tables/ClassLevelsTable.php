<?php

namespace App\Filament\App\Resources\ClassLevels\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClassLevelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Level')
                    ->weight('bold')
                    ->description(fn ($record) => config('academics.curricula')[$record->curriculum] ?? 'Curriculum not set')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('school_classes_count')
                    ->label('Classes')
                    ->counts('schoolClasses')
                    ->sortable(),

                TextColumn::make('description')
                    ->placeholder('—')
                    ->limit(50)
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
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
