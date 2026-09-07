<?php

namespace App\Filament\Resources\Schools\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SchoolForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),

                TextInput::make('slug')
                    ->required(),

                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),

                TextInput::make('phone')
                    ->tel(),

                TextInput::make('nssf_employer_number')
                    ->label('NSSF employer number')
                    ->placeholder('e.g. ER/12345'),

                TextInput::make('tin_number')
                    ->label('TIN number')
                    ->placeholder('e.g. 1001234567'),

                TextInput::make('address'),

                TextInput::make('city'),

                Select::make('country')
                    ->options([
                        'Uganda' => 'Uganda',
                        'Kenya' => 'Kenya',
                        'Tanzania' => 'Tanzania',
                        'Rwanda' => 'Rwanda',
                        'South Sudan' => 'South Sudan',
                        'Burundi' => 'Burundi',
                        'Nigeria' => 'Nigeria',
                        'Ghana' => 'Ghana',
                    ])
                    ->searchable()
                    ->native(false),

                FileUpload::make('logo')
                    ->label('School Logo')
                    ->image()
                    ->disk('public')
                    ->directory('school-logos')
                    ->visibility('public')
                    ->acceptedFileTypes([
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                    ])
                    ->maxSize(2048),

                TextInput::make('timezone')
                    ->required()
                    ->default('Africa/Kampala'),

                TextInput::make('currency')
                    ->required()
                    ->default('UGX'),

                Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'inactive' => 'Inactive',
                    ])
                    ->default('active')
                    ->required(),
            ]);
    }
}