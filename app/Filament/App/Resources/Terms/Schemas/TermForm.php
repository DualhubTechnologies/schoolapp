<?php

namespace App\Filament\App\Resources\Terms\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class TermForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Term')
                    ->description('A teaching period inside an academic year. Add as many as your school uses.')
                    ->columns(2)
                    ->schema([
                        Select::make('academic_year_id')
                            ->label('Academic year')
                            ->relationship(
                                'academicYear',
                                'name',
                                fn (Builder $query) => static::scopeToSchool($query),
                            )
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('name')
                            ->label('Term name')
                            ->required()
                            ->maxLength(50)
                            ->placeholder('e.g. Term 1'),

                        TextInput::make('sequence')
                            ->label('Order in the year')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->default(1)
                            ->helperText('1 for the first term, 2 for the second, and so on. This is how the system knows which term came before when carrying balances forward.'),

                        // Same pattern as HouseForm: defaulted to the signed-in
                        // user's school and locked unless they're Super Admin.
                        Select::make('school_id')
                            ->label('School')
                            ->relationship('school', 'name')
                            ->default(fn () => auth()->user()->school_id)
                            ->disabled(fn () => ! auth()->user()->hasRole('Super Admin'))
                            ->dehydrated()
                            ->required(),

                        DatePicker::make('start_date')
                            ->label('Starts on'),

                        DatePicker::make('end_date')
                            ->label('Ends on')
                            ->afterOrEqual('start_date'),

                        Toggle::make('is_current')
                            ->label('Current term')
                            ->helperText('Only one term can be current. Invoices and statements default to this one.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function scopeToSchool(Builder $query): Builder
    {
        $user = auth()->user();

        if ($user && ! $user->hasRole('Super Admin')) {
            $query->where('school_id', $user->school_id);
        }

        return $query;
    }
}
