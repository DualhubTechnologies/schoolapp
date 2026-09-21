<?php

namespace App\Filament\App\Resources\ResidencyTypes;

use App\Filament\App\Resources\ResidencyTypes\Pages\CreateResidencyType;
use App\Filament\App\Resources\ResidencyTypes\Pages\EditResidencyType;
use App\Filament\App\Resources\ResidencyTypes\Pages\ListResidencyTypes;
use App\Filament\App\Resources\ResidencyTypes\Schemas\ResidencyTypeForm;
use App\Filament\App\Resources\ResidencyTypes\Tables\ResidencyTypesTable;
use App\Models\ResidencyType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ResidencyTypeResource extends Resource
{
    protected static ?string $model = ResidencyType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|\UnitEnum|null $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Residency Types';

    protected static ?string $modelLabel = 'residency type';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ResidencyTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ResidencyTypesTable::configure($table);
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
            'index' => ListResidencyTypes::route('/'),
            'create' => CreateResidencyType::route('/create'),
            'edit' => EditResidencyType::route('/{record}/edit'),
        ];
    }
}
