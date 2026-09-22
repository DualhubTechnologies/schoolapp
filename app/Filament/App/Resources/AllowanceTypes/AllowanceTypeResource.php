<?php

namespace App\Filament\App\Resources\AllowanceTypes;

use App\Filament\App\Resources\AllowanceTypes\Pages\CreateAllowanceType;
use App\Filament\App\Resources\AllowanceTypes\Pages\EditAllowanceType;
use App\Filament\App\Resources\AllowanceTypes\Pages\ListAllowanceTypes;
use App\Filament\App\Resources\AllowanceTypes\Schemas\AllowanceTypeForm;
use App\Filament\App\Resources\AllowanceTypes\Tables\AllowanceTypesTable;
use App\Models\AllowanceType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AllowanceTypeResource extends Resource
{
    use \App\Filament\Concerns\GatedByModule;

    protected static ?string $model = AllowanceType::class;


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?int $navigationSort = 4;

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
        return \App\Support\PayrollAccess::allowed();
    }

    /**
     * Staff pay is confidential: School Admin and Accountant only.
     */
    public static function canViewAny(): bool
    {
        return \App\Support\PayrollAccess::allowed();
    }
}
