<?php

namespace App\Filament\App\Resources\Staff;

use App\Filament\App\Resources\Staff\Pages\CreateStaff;
use App\Filament\App\Resources\Staff\Pages\EditStaff;
use App\Filament\App\Resources\Staff\Pages\ListStaff;
use App\Filament\App\Resources\Staff\RelationManagers;
use App\Filament\App\Resources\Staff\Schemas\StaffForm;
use App\Filament\App\Resources\Staff\Tables\StaffTable;
use App\Models\Staff;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StaffResource extends Resource
{
    protected static ?string $model = Staff::class;


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'name';
    protected static ?int $navigationSort = 1;

    /**
     * "Jane Akello (STF-004)".
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        if (! $record) {
            return null;
        }

        return $record->staff_no ? "{$record->name} ({$record->staff_no})" : $record->name;
    }

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
        return \App\Support\PayrollAccess::allowed();
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

    /**
     * Staff pay is confidential: School Admin and Accountant only.
     */
    public static function canViewAny(): bool
    {
        return \App\Support\PayrollAccess::allowed();
    }
}
