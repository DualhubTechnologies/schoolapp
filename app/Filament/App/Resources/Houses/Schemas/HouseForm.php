<?php

namespace App\Filament\App\Resources\Houses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class HouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('House name')
                    ->required()
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('school_id', $get('school_id') ?? auth()->user()?->school_id))
                    ->validationMessages(['unique' => 'A house with this name already exists.'])
                    ->maxLength(100)
                    ->placeholder('e.g. Kabalega'),

                TextInput::make('capacity')
                    ->label('Student Capacity')
                    ->numeric()
                    ->minValue(1)
                    ->placeholder('Unlimited')
                    ->helperText('Leave blank for unlimited capacity.'),

                // Same pattern as UserForm: defaulted to the signed-in
                // user's school and locked unless they're Super Admin.
                Select::make('school_id')
                    ->label('School')
                    ->relationship('school', 'name')
                    ->default(fn () => auth()->user()->school_id)
                    ->disabled(fn () => ! auth()->user()->hasRole('Super Admin'))
                    ->dehydrated()
                    ->required(),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Inactive houses stay on existing students but are hidden when assigning new ones.'),
            ]);
    }
}
