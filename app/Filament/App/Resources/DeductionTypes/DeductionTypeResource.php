<?php

namespace App\Filament\App\Resources\DeductionTypes;

use App\Filament\App\Resources\DeductionTypes\Pages\CreateDeductionType;
use App\Filament\App\Resources\DeductionTypes\Pages\EditDeductionType;
use App\Filament\App\Resources\DeductionTypes\Pages\ListDeductionTypes;
use App\Filament\App\Resources\DeductionTypes\Schemas\DeductionTypeForm;
use App\Filament\App\Resources\DeductionTypes\Tables\DeductionTypesTable;
use App\Models\DeductionType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeductionTypeResource extends Resource
{
    use \App\Filament\Concerns\GatedByModule;

    protected static ?string $model = DeductionType::class;


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMinusCircle;

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

public static function getNavigationGroup(): ?string
{
    return 'Human Resources';
}

    public static function form(Schema $schema): Schema
    {
        return DeductionTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeductionTypesTable::configure($table);
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
    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\PayrollAccess::allowed();
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
            'index' => ListDeductionTypes::route('/'),
            'create' => CreateDeductionType::route('/create'),
            'edit' => EditDeductionType::route('/{record}/edit'),
        ];
    }

    /**
     * Staff pay is confidential: School Admin and Accountant only.
     */
    public static function canViewAny(): bool
    {
        return \App\Support\PayrollAccess::allowed();
    }
}
