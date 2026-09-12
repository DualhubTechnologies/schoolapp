<?php

namespace App\Filament\App\Resources\Sections\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SectionForm
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
                Select::make('school_class_id')
                    ->label('Class')
                    ->relationship('schoolClass', 'name', fn (Builder $query) => static::scopeToSchool($query))
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name')
                    ->label('Section name')
                    ->placeholder('e.g. A, East, Blue')
                    ->required(),
                Select::make('class_teacher_id')
                    ->label('Class teacher')
                    ->relationship('classTeacher', 'name', fn (Builder $query) => static::scopeToSchool($query)->where('status', 'active'))
                    ->searchable()
                    ->preload(),
                TextInput::make('capacity')
                    ->label('Capacity')
                    ->helperText('Maximum students. Leave blank for no limit.')
                    ->numeric()
                    ->minValue(1),
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