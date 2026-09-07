<?php

namespace App\Filament\Resources\Staff\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StaffForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Full name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('nssf_number')
                    ->label('NSSF number')
                    ->placeholder('e.g. NF/12345678'),
                TextInput::make('tin_number')
                    ->label('TIN number')
                    ->placeholder('e.g. 1001234567'),
                Select::make('school_id')
                    ->relationship('school', 'name')
                    ->default(fn () => auth()->user()->school_id)
                    ->disabled(fn () => ! auth()->user()->hasRole('Super Admin'))
                    ->dehydrated()
                    ->required(),
                TextInput::make('staff_no')
                    ->label('Staff number')
                    ->required(),
                TextInput::make('position')
                    ->required(),
                TextInput::make('department'),
                TextInput::make('phone')
                    ->tel(),
                DatePicker::make('employment_date')
                    ->required(),
                Select::make('status')
                    ->options(['active' => 'Active', 'on_leave' => 'On leave', 'terminated' => 'Terminated'])
                    ->default('active')
                    ->required(),
            ]);
    }
}