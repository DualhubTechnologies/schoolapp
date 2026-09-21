<?php

namespace App\Filament\App\Resources\Billing;

use App\Filament\App\Resources\Billing\Pages\ListBilling;
use App\Filament\App\Resources\Billing\Tables\BillingTable;
use App\Models\StudentCharge;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bill Students — where charges are put onto accounts.
 *
 * The rows are individual charges. The printable invoice is rendered live
 * from the ledger on the Student Account page, so it always shows the
 * balance as it stands today.
 */
class BillingResource extends Resource
{
    protected static ?string $model = StudentCharge::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|\UnitEnum|null $navigationGroup = 'Fees';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Billing';

    protected static ?string $modelLabel = 'charge';

    protected static ?string $pluralModelLabel = 'Billing';

    protected static ?string $recordTitleAttribute = 'description';

    public static function table(Table $table): Table
    {
        return BillingTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['student.schoolClass', 'student.residencyType', 'term']);

        $user = auth()->user();

        if ($user && ! $user->hasRole('Super Admin')) {
            $query->where('school_id', $user->school_id);
        }

        return $query;
    }

    // Charges are added through the billing actions, so every one traces
    // back to a fee or a deliberate one-off entry.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBilling::route('/'),
        ];
    }
}
