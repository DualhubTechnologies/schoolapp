<?php

namespace App\Filament\App\Resources\Houses;

use App\Filament\App\Resources\Houses\Pages\CreateHouse;
use App\Filament\App\Resources\Houses\Pages\EditHouse;
use App\Filament\App\Resources\Houses\Pages\ListHouses;
use App\Filament\App\Resources\Houses\Schemas\HouseForm;
use App\Filament\App\Resources\Houses\Tables\HousesTable;
use App\Models\House;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HouseResource extends Resource
{
    use \App\Filament\Concerns\GatedByModule;

    protected static ?string $model = House::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $recordTitleAttribute = 'name';

  
    protected static string|\UnitEnum|null $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return HouseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HousesTable::configure($table);
    }

    /**
     * Scope to the signed-in user's school, matching the pattern used
     * by UserResource. Super Admin sees everything.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if ($user && ! $user->hasRole('Super Admin')) {
            $query->where('school_id', $user->school_id);
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHouses::route('/'),
            'create' => CreateHouse::route('/create'),
            'edit' => EditHouse::route('/{record}/edit'),
        ];
    }
}
