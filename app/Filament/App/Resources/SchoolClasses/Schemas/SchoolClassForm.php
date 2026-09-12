<?php

namespace App\Filament\App\Resources\SchoolClasses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SchoolClassForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('school_id')
                    ->relationship('school', 'name')
                    ->default(fn () => auth()->user()->school_id)
                    ->disabled(fn () => ! auth()->user()->hasRole('Super Admin'))
                    ->dehydrated()
                    ->required(),
                TextInput::make('name')
                    ->label('Class name')
                    ->placeholder('e.g. Senior 1, P.4')
                    ->required(),
                TextInput::make('level')
                    ->label('Sort order')
                    ->helperText('Numeric position in the academic ladder — 1 for the lowest class. Controls list order.')
                    ->numeric()
                    ->minValue(1),
            ]);
    }
}