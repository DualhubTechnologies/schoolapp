<?php

namespace App\Filament\App\Resources\Staff\Tables;

use App\Models\Staff;
use App\Models\User;
use App\Support\EmailCheck;
use App\Support\PasswordStrength;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class StaffTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('currentSalary'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Staff member')
                    ->weight('semibold')
                    ->description(fn (Staff $record) => collect([$record->staff_no, $record->position])->filter()->implode(' · '))
                    ->searchable(['name', 'staff_no', 'position'])
                    ->sortable(),
                TextColumn::make('school.name')
                    ->label('School')
                    ->visible(fn () => auth()->user()?->hasRole('Super Admin')),
                TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state === 'teaching' ? 'Teaching' : 'Non-teaching')
                    ->color(fn (?string $state) => $state === 'teaching' ? 'primary' : 'gray'),
                TextColumn::make('department')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('employment_type')
                    ->label('Type')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state) => Staff::EMPLOYMENT_TYPES[$state] ?? $state)
                    ->toggleable(),
                TextColumn::make('currentSalary.base_salary')
                    ->label('Basic salary')
                    ->numeric()
                    ->alignEnd()
                    ->placeholder('Not set')
                    ->color(fn (Staff $record) => $record->currentSalary ? null : 'danger'),
                TextColumn::make('statutory')
                    ->label('TIN / NSSF')
                    ->state(fn (Staff $record) => match (true) {
                        ! $record->tin_number && ! $record->nssf_number => 'Both missing',
                        ! $record->tin_number => 'No TIN',
                        $record->pays_nssf && ! $record->nssf_number => 'No NSSF no.',
                        default => 'Complete',
                    })
                    ->badge()
                    ->color(fn (string $state) => $state === 'Complete' ? 'success' : 'warning'),
                TextColumn::make('phone')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                IconColumn::make('user_id')
                    ->label('Login')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->user_id !== null)
                    ->toggleable(),
                TextColumn::make('employment_date')
                    ->label('Employed')
                    ->date('j M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Staff::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'on_leave' => 'warning',
                        default => 'danger',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(Staff::STATUSES)
                    ->default('active'),
                SelectFilter::make('category')
                    ->label('Category')
                    ->options(Staff::CATEGORIES),
                SelectFilter::make('employment_type')
                    ->label('Type')
                    ->options(Staff::EMPLOYMENT_TYPES),
                SelectFilter::make('department')
                    ->options(fn () => Staff::query()
                        ->where('school_id', auth()->user()?->school_id)
                        ->whereNotNull('department')
                        ->distinct()
                        ->orderBy('department')
                        ->pluck('department', 'department')
                        ->all()),
            ])
            ->recordActions([
                Action::make('createLogin')
                    ->label('Create Login')
                    ->icon('heroicon-o-key')
                    ->color('success')
                    ->visible(fn ($record) => $record->user_id === null)
                    ->form([
                        EmailCheck::apply(TextInput::make('email'))
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->unique(table: 'users', column: 'email', ignoreRecord: false)
                            ->default(fn ($record) => $record->email),
                        PasswordStrength::meter(TextInput::make('password'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->rule(Password::default())
                            ->same('passwordConfirmation'),
                        PasswordStrength::matches(TextInput::make('passwordConfirmation'))
                            ->label('Confirm password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->dehydrated(false),
                        Select::make('roles')
                            ->multiple()
                            ->required()
                            ->options(fn () => Role::whereIn('name', ['Teacher', 'Staff', 'Accountant', 'School Admin'])
                                ->pluck('name', 'name')
                                ->when(! auth()->user()?->hasRole(['School Admin', 'Super Admin']), fn ($roles) => $roles->except(['School Admin']))),
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
            ->emptyStateHeading('No staff yet')
            ->emptyStateDescription('Add staff, then set each person\'s salary in their record.')
            ->emptyStateIcon('heroicon-o-user-group');
    }
}
