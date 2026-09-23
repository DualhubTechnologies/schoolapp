<?php

namespace App\Filament\Admin\Resources\Schools;

use App\Filament\Admin\Resources\Schools\Pages\CreateSchool;
use App\Filament\Admin\Resources\Schools\Pages\EditSchool;
use App\Filament\Admin\Resources\Schools\Pages\ListSchools;
use App\Filament\Admin\Resources\Schools\Schemas\SchoolForm;
use App\Filament\Admin\Resources\Schools\Tables\SchoolsTable;
use App\Models\School;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SchoolResource extends Resource
{
    protected static ?string $model = School::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return SchoolForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SchoolsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SubscriptionsRelationManager::class,
            RelationManagers\SubscriptionPaymentsRelationManager::class,
            RelationManagers\ActivationCodesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSchools::route('/'),
            'create' => CreateSchool::route('/create'),
            'edit' => EditSchool::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Platform Management';
    }
}
