<?php

namespace App\Filament\Resources\Students\Tables;

use App\Models\Student;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StudentTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&background=random'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('admission_no')
                    ->label('Adm. No.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('schoolClass.name')
                    ->label('Class')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('section.name')
                    ->label('Section')
                    ->placeholder('—'),
                TextColumn::make('gender')
                    ->formatStateUsing(fn (?string $state) => Student::GENDERS[$state] ?? '—')
                    ->toggleable(),
                TextColumn::make('guardian.name')
                    ->label('Guardian')
                    ->placeholder('Not linked')
                    ->searchable(),
                TextColumn::make('guardian.phone')
                    ->label('Guardian phone')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Student::STATUSES[$state] ?? $state)
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'graduated' => 'info',
                        'withdrawn' => 'danger',
                        'transferred' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('date_of_birth')
                    ->label('D.O.B.')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('admission_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                SelectFilter::make('section_id')
                    ->label('Section')
                    ->relationship('section', 'name')
                    ->preload(),
                SelectFilter::make('status')
                    ->options(Student::STATUSES)
                    ->default('active'),
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