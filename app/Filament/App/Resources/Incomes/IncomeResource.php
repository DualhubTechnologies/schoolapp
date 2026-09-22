<?php

namespace App\Filament\App\Resources\Incomes;

use App\Filament\App\Resources\Incomes\Pages\ManageIncomes;
use App\Filament\Support\FinanceEntryResource;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * Money received other than school fees: capitation grants, donations,
 * sales, hire. (Fees are counted automatically from receipts.)
 */
class IncomeResource extends FinanceEntryResource
{
    protected static string $entryType = 'income';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Other Income';

    protected static ?string $modelLabel = 'income';

    protected static ?string $pluralModelLabel = 'Other Income';

    protected static ?string $slug = 'other-income';

    public static function getPages(): array
    {
        return ['index' => ManageIncomes::route('/')];
    }
}
