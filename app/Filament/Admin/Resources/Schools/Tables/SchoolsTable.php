<?php

namespace App\Filament\Admin\Resources\Schools\Tables;

use App\Models\School;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SchoolsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')
                    ->label('')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name='
                        . urlencode($record->name) . '&background=random'),

                TextColumn::make('name')
                    ->label('School')
                    ->weight('bold')
                    ->description(fn (School $record): ?string => $record->unique_code)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('school_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => School::TYPES[$state] ?? 'Not set')
                    ->color(fn (?string $state): string => match ($state) {
                        'secondary' => 'info',
                        'primary' => 'success',
                        default => 'warning',
                    }),

                TextColumn::make('students_count')
                    ->label('Students')
                    ->counts('students')
                    ->sortable(),

                TextColumn::make('city')
                    ->label('City / District')
                    ->description(fn (School $record): ?string => $record->country)
                    ->searchable(),

                TextColumn::make('phone')
                    ->label('Telephone')
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'inactive' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('currency')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('timezone')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('address')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('school_type')
                    ->label('Type')
                    ->options(School::TYPES),

                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'inactive' => 'Inactive',
                    ]),
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