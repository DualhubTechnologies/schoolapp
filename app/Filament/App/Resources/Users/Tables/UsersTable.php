<?php

namespace App\Filament\App\Resources\Users\Tables;

use App\Filament\Support\ConfirmWithPassword;
use App\Models\User;
use App\Support\Modules;
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
            ->emptyStateHeading('Only you so far')
            ->emptyStateDescription('Give your bursar, teachers and director of studies their own logins, each seeing only their part of the system.')
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
                    ->state(fn (User $record) => Modules::hasFullAccess($record)
                        ? ['Everything']
                        : collect(Modules::forUser($record))
                            ->map(fn ($key) => Modules::LIST[$key][0] ?? $key)
                            ->values()->all())
                    ->badge()
                    ->color(fn (User $record) => $record->modules !== null ? 'warning' : 'gray')
                    ->tooltip(fn (User $record) => $record->modules !== null ? 'Chosen for this user' : 'Role defaults')
                    ->placeholder('Nothing')
                    ->wrap(),
                TextColumn::make('last_seen_at')
                    ->label('Last seen')
                    ->formatStateUsing(fn (User $record): string => $record->isActiveNow() ? 'Active now' : (string) $record->last_seen_at?->diffForHumans())
                    ->badge(fn (User $record): bool => $record->isActiveNow())
                    ->color(fn (User $record): ?string => $record->isActiveNow() ? 'success' : null)
                    ->dateTimeTooltip('d M Y H:i')
                    ->placeholder('Never')
                    ->sortable(),
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
                    ConfirmWithPassword::on(DeleteBulkAction::make()),
                ]),
            ]);
    }
}
