<?php

namespace App\Filament\Resources\Staff\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class StaffTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('staff_no')
                    ->label('Staff No.')
                    ->searchable(),
                TextColumn::make('school.name')
                    ->label('School')
                    ->searchable(),
                TextColumn::make('position')
                    ->searchable(),
                TextColumn::make('department')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                IconColumn::make('user_id')
                    ->label('Has login')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->user_id !== null),
                TextColumn::make('employment_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'on_leave' => 'warning',
                        'terminated' => 'danger',
                    }),
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
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'on_leave' => 'On leave',
                        'terminated' => 'Terminated',
                    ]),
            ])
            ->recordActions([
                Action::make('createLogin')
                    ->label('Create Login')
                    ->icon('heroicon-o-key')
                    ->color('success')
                    ->visible(fn ($record) => $record->user_id === null)
                    ->form([
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->unique(table: 'users', column: 'email', ignoreRecord: false)
                            ->default(fn ($record) => $record->email),
                        TextInput::make('password')
                            ->password()
                            ->required()
                            ->minLength(8),
                        Select::make('roles')
                            ->multiple()
                            ->required()
                            ->options(fn () => Role::pluck('name', 'name')),
                    ])
                    ->action(function (array $data, $record) {
                        $user = User::create([
                            'name' => $record->name,
                            'email' => $data['email'],
                            'password' => Hash::make($data['password']),
                            'school_id' => $record->school_id,
                        ]);

                        $user->assignRole($data['roles']);

                        $record->update(['user_id' => $user->id]);

                        Notification::make()
                            ->title('Login created successfully')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}