<?php

namespace App\Filament\App\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('school.name')
                    ->label('School')
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->searchable(),
                TextColumn::make('access')
                    ->label('Can open')
                    ->state(fn (\App\Models\User $record) => \App\Support\Modules::hasFullAccess($record)
                        ? ['Everything']
                        : collect(\App\Support\Modules::forUser($record))
                            ->map(fn ($key) => \App\Support\Modules::LIST[$key][0] ?? $key)
                            ->values()->all())
                    ->badge()
                    ->color(fn (\App\Models\User $record) => $record->modules !== null ? 'warning' : 'gray')
                    ->tooltip(fn (\App\Models\User $record) => $record->modules !== null ? 'Chosen for this user' : 'Role defaults')
                    ->placeholder('Nothing')
                    ->wrap(),
                TextColumn::make('staff.name')
                    ->label('Staff record')
                    ->placeholder('Not linked')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
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
            ]);
    }
}