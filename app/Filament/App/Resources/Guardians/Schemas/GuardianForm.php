<?php

namespace App\Filament\App\Resources\Guardians\Schemas;

use App\Models\Guardian;
use App\Support\EmailCheck;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GuardianForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Full name')
                    ->required(),
                Select::make('school_id')
                    ->relationship('school', 'name')
                    ->default(fn () => auth()->user()->school_id)
                    ->disabled(fn () => ! auth()->user()->hasRole('Super Admin'))
                    ->dehydrated()
                    ->required(),
                Select::make('relationship')
                    ->options(Guardian::RELATIONSHIPS)
                    ->default('guardian')
                    ->required(),
                TextInput::make('phone')
                    ->label('Primary phone')
                    ->tel()
                    ->required(),
                TextInput::make('alt_phone')
                    ->label('Alternative phone')
                    ->tel(),
                EmailCheck::apply(TextInput::make('email'))
                    ->label('Email address')
                    ->email(),
                TextInput::make('occupation'),
                TextInput::make('national_id')
                    ->label('National ID (NIN)'),
                Textarea::make('address')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }
}
