<?php

namespace App\Filament\App\Resources\FeeStructures;

use App\Filament\App\Resources\FeeStructures\Pages\CreateFeeStructure;
use App\Filament\App\Resources\FeeStructures\Pages\EditFeeStructure;
use App\Filament\App\Resources\FeeStructures\Pages\ListFeeStructures;
use App\Filament\App\Resources\FeeStructures\Schemas\FeeStructureForm;
use App\Filament\App\Resources\FeeStructures\Tables\FeeStructureTable;
use App\Models\FeeStructure;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FeeStructureResource extends Resource
{
    use \App\Filament\Concerns\GatedByModule;

    protected static ?string $model = FeeStructure::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $navigationLabel = 'Fee Setup';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'Fee Structure';

    /**
     * "Tuition — S1 (Boarding)", for page headings and breadcrumbs.
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        if (! $record) {
            return null;
        }

        $title = collect([$record->name, $record->schoolClass?->name])->filter()->implode(' — ');

        return $record->residencyType ? "{$title} ({$record->residencyType->name})" : $title;
    }

    public static function form(Schema $schema): Schema
    {
        return FeeStructureForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FeeStructureTable::configure($table);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return ! auth()->user()?->hasRole('Super Admin');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Fees';
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

    public static function getPages(): array
    {
        return [
            'index' => ListFeeStructures::route('/'),
            'create' => CreateFeeStructure::route('/create'),
            'edit' => EditFeeStructure::route('/{record}/edit'),
        ];
    }
}
