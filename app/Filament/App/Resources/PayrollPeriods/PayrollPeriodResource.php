<?php

namespace App\Filament\App\Resources\PayrollPeriods;

use App\Filament\App\Resources\PayrollPeriods\Pages\CreatePayrollPeriod;
use App\Filament\App\Resources\PayrollPeriods\Pages\EditPayrollPeriod;
use App\Filament\App\Resources\PayrollPeriods\Pages\ListPayrollPeriods;
use App\Filament\App\Resources\PayrollPeriods\Schemas\PayrollPeriodForm;
use App\Filament\App\Resources\PayrollPeriods\Tables\PayrollPeriodsTable;
use App\Models\PayrollPeriod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PayrollPeriodResource extends Resource
{
    protected static ?string $model = PayrollPeriod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Payroll';

    protected static ?string $modelLabel = 'Payroll Period';

    protected static ?string $pluralModelLabel = 'Payroll';

    public static function form(Schema $schema): Schema
    {
        return PayrollPeriodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayrollPeriodsTable::configure($table);
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
    protected static ?int $navigationSort = 2;
    public static function getRelations(): array
    {
        return [
            RelationManagers\PayrollEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayrollPeriods::route('/'),
            'create' => CreatePayrollPeriod::route('/create'),
            'edit' => EditPayrollPeriod::route('/{record}/edit'),
        ];
    }
    public static function shouldRegisterNavigation(): bool
    {
        return ! auth()->user()?->hasRole('Super Admin');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Human Resources';
    }
}