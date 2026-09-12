<?php

namespace App\Filament\App\Resources\Staff;

use App\Filament\App\Resources\Staff\RelationManagers;
use App\Filament\App\Resources\Staff\Pages\CreateStaff;
use App\Filament\App\Resources\Staff\Pages\EditStaff;
use App\Filament\App\Resources\Staff\Pages\ListStaff;
use App\Filament\App\Resources\Staff\Schemas\StaffForm;
use App\Filament\App\Resources\Staff\Tables\StaffTable;
use App\Models\Staff;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StaffResource extends Resource
{
    protected static ?string $model = Staff::class;


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'staff_no';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return StaffForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StaffTable::configure($table);
    }
    public static function shouldRegisterNavigation(): bool
    {
        return ! auth()->user()?->hasRole('Super Admin');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Human Resources';
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
        RelationManagers\SalariesRelationManager::class,
        RelationManagers\AllowancesRelationManager::class,
        RelationManagers\DeductionsRelationManager::class,
        RelationManagers\BankDetailsRelationManager::class,
        RelationManagers\ArrearsRelationManager::class,
    ];
}

    public static function getPages(): array
    {
        return [
            'index' => ListStaff::route('/'),
            'create' => CreateStaff::route('/create'),
            'edit' => EditStaff::route('/{record}/edit'),
        ];
    }
}