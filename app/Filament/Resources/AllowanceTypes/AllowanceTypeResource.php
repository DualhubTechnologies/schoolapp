<?php

namespace App\Filament\Resources\AllowanceTypes;

use App\Filament\Resources\AllowanceTypes\Pages\CreateAllowanceType;
use App\Filament\Resources\AllowanceTypes\Pages\EditAllowanceType;
use App\Filament\Resources\AllowanceTypes\Pages\ListAllowanceTypes;
use App\Filament\Resources\AllowanceTypes\Schemas\AllowanceTypeForm;
use App\Filament\Resources\AllowanceTypes\Tables\AllowanceTypesTable;
use App\Models\AllowanceType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AllowanceTypeResource extends Resource
{
    protected static ?string $model = AllowanceType::class;


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $recordTitleAttribute = 'name';

public static function getNavigationGroup(): ?string
{
    return 'Human Resources';
}

    public static function form(Schema $schema): Schema
    {
        return AllowanceTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AllowanceTypesTable::configure($table);
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAllowanceTypes::route('/'),
            'create' => CreateAllowanceType::route('/create'),
            'edit' => EditAllowanceType::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return ! auth()->user()?->hasRole('Super Admin');
    }
}