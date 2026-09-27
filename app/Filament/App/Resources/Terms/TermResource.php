<?php

namespace App\Filament\App\Resources\Terms;

use App\Filament\App\Resources\Terms\Pages\CreateTerm;
use App\Filament\App\Resources\Terms\Pages\EditTerm;
use App\Filament\App\Resources\Terms\Pages\ListTerms;
use App\Filament\App\Resources\Terms\Schemas\TermForm;
use App\Filament\App\Resources\Terms\Tables\TermsTable;
use App\Filament\Concerns\GatedByModule;
use App\Models\Term;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TermResource extends Resource
{
    use GatedByModule;

    protected static ?string $model = Term::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|\UnitEnum|null $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Terms';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TermForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TermsTable::configure($table);
    }

    /**
     * Scope to the signed-in user's school. Super Admin sees everything.
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
            'index' => ListTerms::route('/'),
            'create' => CreateTerm::route('/create'),
            'edit' => EditTerm::route('/{record}/edit'),
        ];
    }
}
