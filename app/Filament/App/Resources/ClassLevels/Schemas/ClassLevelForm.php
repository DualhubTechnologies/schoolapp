<?php

namespace App\Filament\App\Resources\ClassLevels\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class ClassLevelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Class level')
                    ->description('Groups classes for fee structures and printed documents. Your school starts with the levels its type implies — edit them to match how your school words them.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Level name')
                            ->placeholder('e.g. O-Level, A-Level')
                            ->required()
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('school_id', $get('school_id') ?? auth()->user()?->school_id))
                            ->validationMessages(['unique' => 'A class level with this name already exists.'])
                            ->maxLength(60),

                        // Decides how the level's classes are graded:
                        // aggregates & divisions, A–E levels, or A-Level points.
                        Select::make('curriculum')
                            ->label('Curriculum')
                            ->options(config('academics.curricula'))
                            ->native(false)
                            ->helperText('Decides the subjects, grading and report cards for classes in this level.'),

                        TextInput::make('sort_order')
                            ->label('Order')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required()
                            ->helperText('Lowest first — 1 for the youngest level. Controls the order on printed fee structures.'),

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
                            ->helperText('Inactive levels stay on existing classes but are hidden when creating new ones.'),

                        Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
