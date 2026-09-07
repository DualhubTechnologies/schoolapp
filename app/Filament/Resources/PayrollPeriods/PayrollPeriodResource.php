<?php

namespace App\Filament\Resources\PayrollPeriods;

use App\Filament\Resources\PayrollPeriods\Pages\CreatePayrollPeriod;
use App\Filament\Resources\PayrollPeriods\Pages\EditPayrollPeriod;
use App\Filament\Resources\PayrollPeriods\Pages\ListPayrollPeriods;
use App\Filament\Resources\PayrollPeriods\Schemas\PayrollPeriodForm;
use App\Filament\Resources\PayrollPeriods\Tables\PayrollPeriodsTable;
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
}