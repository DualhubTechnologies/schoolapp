<?php

namespace App\Filament\App\Resources\SchoolClasses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class SchoolClassForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Class')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Class name')
                            ->placeholder(fn () => \App\Support\SchoolType::isPrimary() ? 'e.g. P.4, Primary Five, Baby Class' : ('e.g. S1, Senior 5'))
                            ->required()
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('school_id', $get('school_id') ?? auth()->user()?->school_id))
                            ->validationMessages(['unique' => 'A class with this name already exists.']),

                        // Which level this class belongs to. A class sits in
                        // exactly one -- S1 is O-Level, S5 is A-Level -- and
                        // it is what groups classes on the fees structure.
                        Select::make('class_level_id')
                            ->label('Level')
                            ->relationship(
                                'classLevel',
                                'name',
                                fn (Builder $query) => $query
                                    ->where('school_id', auth()->user()?->school_id)
                                    ->where('is_active', true)
                                    ->orderBy('sort_order'),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Manage these under Academics → Class Levels.'),

                        Select::make('school_id')
                            ->label('School')
                            ->relationship('school', 'name')
                            ->default(fn () => auth()->user()->school_id)
                            ->disabled(fn () => ! auth()->user()->hasRole('Super Admin'))
                            ->dehydrated()
                            ->required(),

                        // Named `level` in the database for historical
                        // reasons, but it is a sort position, not an
                        // academic level -- that is class_level_id above.
                        TextInput::make('level')
                            ->label('Sort order')
                            ->helperText('Numeric position in the academic ladder — 1 for the lowest class. Controls list order.')
                            ->numeric()
                            ->minValue(1),
                    ]),
            ]);
    }
}
