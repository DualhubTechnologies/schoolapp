<?php

namespace App\Filament\App\Resources\AcademicYears;

use App\Filament\App\Resources\AcademicYears\Pages\CreateAcademicYear;
use App\Filament\App\Resources\AcademicYears\Pages\EditAcademicYear;
use App\Filament\App\Resources\AcademicYears\Pages\ListAcademicYears;
use App\Filament\App\Resources\AcademicYears\Schemas\AcademicYearForm;
use App\Filament\App\Resources\AcademicYears\Tables\AcademicYearsTable;
use App\Models\AcademicYear;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AcademicYearResource extends Resource
{
    use \App\Filament\Concerns\GatedByModule;

    protected static ?string $model = AcademicYear::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Academic Years';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AcademicYearForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AcademicYearsTable::configure($table);
    }

    /**
     * Scope to the signed-in user's school, matching the pattern used by
     * HouseResource. Super Admin sees everything.
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
            'index' => ListAcademicYears::route('/'),
            'create' => CreateAcademicYear::route('/create'),
            'edit' => EditAcademicYear::route('/{record}/edit'),
        ];
    }
}
