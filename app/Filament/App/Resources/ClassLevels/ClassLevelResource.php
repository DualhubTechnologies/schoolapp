<?php

namespace App\Filament\App\Resources\ClassLevels;

use App\Filament\App\Resources\ClassLevels\Pages\CreateClassLevel;
use App\Filament\App\Resources\ClassLevels\Pages\EditClassLevel;
use App\Filament\App\Resources\ClassLevels\Pages\ListClassLevels;
use App\Filament\App\Resources\ClassLevels\Schemas\ClassLevelForm;
use App\Filament\App\Resources\ClassLevels\Tables\ClassLevelsTable;
use App\Filament\Concerns\GatedByModule;
use App\Models\ClassLevel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClassLevelResource extends Resource
{
    use GatedByModule;

    protected static ?string $model = ClassLevel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Class Levels';

    protected static ?string $modelLabel = 'class level';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ClassLevelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClassLevelsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if ($user && ! $user->hasRole('Super Admin')) {
            $query->where('school_id', $user->school_id);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClassLevels::route('/'),
            'create' => CreateClassLevel::route('/create'),
            'edit' => EditClassLevel::route('/{record}/edit'),
        ];
    }
}
