<?php

namespace App\Filament\App\Resources\Expenses;

use App\Filament\App\Resources\Expenses\Pages\ManageExpenses;
use App\Filament\Support\FinanceEntryResource;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * Money the school spends (salaries are added automatically from payroll).
 */
class ExpenseResource extends FinanceEntryResource
{
    protected static string $entryType = 'expense';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Expenses';

    protected static ?string $modelLabel = 'expense';

    protected static ?string $pluralModelLabel = 'Expenses';

    protected static ?string $slug = 'expenses';

    public static function getPages(): array
    {
        return ['index' => ManageExpenses::route('/')];
    }
}
