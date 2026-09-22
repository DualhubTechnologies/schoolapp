<?php

namespace App\Filament\App\Resources\FinanceCategories;

use App\Filament\App\Resources\FinanceCategories\Pages\ManageFinanceCategories;
use App\Models\FinanceCategory;
use App\Support\FinanceAccess;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Income and expense heads used by entries, budgets and reports.
 */
class FinanceCategoryResource extends Resource
{
    use \App\Filament\Concerns\GatedByModule;

    protected static ?string $model = FinanceCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Categories';

    protected static ?string $modelLabel = 'category';

    public static function getEloquentQuery(): Builder
    {
        FinanceCategory::ensureDefaults(auth()->user()->school_id);

        return parent::getEloquentQuery()->where('school_id', auth()->user()?->school_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('type')
                ->options(['income' => 'Income', 'expense' => 'Expense'])
                ->required()
                ->native(false)
                ->disabled(fn (?FinanceCategory $record) => $record?->isAutomatic()),
            TextInput::make('name')->required()->maxLength(100),
            TextInput::make('sort_order')->label('Order')->numeric()->default(50),
            Toggle::make('is_active')->label('In use')->default(true)->inline(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->groups([Group::make('type')->getTitleFromRecordUsing(fn (FinanceCategory $record) => $record->type === 'income' ? 'Income' : 'Expenditure')])
            ->defaultGroup('type')
            ->defaultSort(fn (Builder $query) => $query->orderBy('sort_order')->orderBy('name'))
            ->columns([
                TextColumn::make('name')->weight('semibold')
                    ->description(fn (FinanceCategory $r) => match ($r->system_key) {
                        'fees' => 'Filled automatically from fee receipts',
                        'payroll' => 'Filled automatically from approved payroll',
                        default => null,
                    }),
                IconColumn::make('is_active')->label('In use')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->paginated(false);
    }

    public static function canViewAny(): bool
    {
        return FinanceAccess::allowed();
    }

    public static function canDelete(Model $record): bool
    {
        return false; // switch off instead: entries point at it
    }

    public static function getPages(): array
    {
        return ['index' => ManageFinanceCategories::route('/')];
    }
}
