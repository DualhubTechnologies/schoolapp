<?php

namespace App\Filament\App\Resources\SalaryArrears;

use App\Filament\App\Resources\SalaryArrears\Pages\CreateSalaryArrear;
use App\Filament\App\Resources\SalaryArrears\Pages\EditSalaryArrear;
use App\Filament\App\Resources\SalaryArrears\Pages\ListSalaryArrears;
use App\Filament\App\Resources\SalaryArrears\Schemas\SalaryArrearForm;
use App\Filament\App\Resources\SalaryArrears\Tables\SalaryArrearsTable;
use App\Models\SalaryArrear;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalaryArrearResource extends Resource
{
    protected static ?string $model = SalaryArrear::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'Salary Arrears';

    protected static ?string $modelLabel = 'Salary Arrear';


    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        $query = SalaryArrear::where('status', 'pending');

        if ($user && ! $user->hasRole('Super Admin')) {
            $query->where('school_id', $user->school_id);
        }

        $count = $query->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    protected static ?string $recordTitleAttribute = 'reason';

    public static function form(Schema $schema): Schema
    {
        return SalaryArrearForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalaryArrearsTable::configure($table);
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

    public static function shouldRegisterNavigation(): bool
    {
        return ! auth()->user()?->hasRole('Super Admin');
    }
    protected static ?int $navigationSort = 3;
    public static function getNavigationGroup(): ?string
    {
        return 'Human Resources';
    }
    public static function getPages(): array
    {
        return [
            'index' => ListSalaryArrears::route('/'),
            'create' => CreateSalaryArrear::route('/create'),
            'edit' => EditSalaryArrear::route('/{record}/edit'),
        ];
    }
}