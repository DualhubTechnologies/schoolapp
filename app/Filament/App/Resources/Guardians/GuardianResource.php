<?php

namespace App\Filament\App\Resources\Guardians;

use App\Filament\App\Resources\Guardians\Pages\CreateGuardian;
use App\Filament\App\Resources\Guardians\Pages\EditGuardian;
use App\Filament\App\Resources\Guardians\Pages\ListGuardians;
use App\Filament\App\Resources\Guardians\Pages\ViewGuardian;
use App\Filament\App\Resources\Guardians\Schemas\GuardianForm;
use App\Filament\App\Resources\Guardians\Tables\GuardiansTable;
use App\Filament\Concerns\GatedByModule;
use App\Models\Guardian;
use App\Support\Modules;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GuardianResource extends Resource
{
    use GatedByModule;

    protected static ?string $model = Guardian::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'phone', 'alt_phone'];
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        if (! $record instanceof Guardian) {
            return [];
        }

        return array_filter(['Phone' => $record->phone, 'Relationship' => $record->relationship ? ucfirst($record->relationship) : null]);
    }

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Guardian';

    protected static ?string $pluralModelLabel = 'Guardians / Parents';

    public static function form(Schema $schema): Schema
    {
        return GuardianForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GuardiansTable::configure($table);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return ! auth()->user()?->hasRole('Super Admin');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Students';
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

    /** Admitting, changing and removing learners is the admissions office's job; teachers only see them. */
    public static function canCreate(): bool
    {
        return Modules::allows('admissions');
    }

    public static function canEdit(Model $record): bool
    {
        return Modules::allows('admissions');
    }

    public static function canDelete(Model $record): bool
    {
        return Modules::allows('admissions');
    }

    public static function canDeleteAny(): bool
    {
        return Modules::allows('admissions');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuardians::route('/'),
            'create' => CreateGuardian::route('/create'),
            'view' => ViewGuardian::route('/{record}'),
            'edit' => EditGuardian::route('/{record}/edit'),
        ];
    }
}
