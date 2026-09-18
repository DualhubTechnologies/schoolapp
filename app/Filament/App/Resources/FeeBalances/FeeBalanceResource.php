<?php

namespace App\Filament\App\Resources\FeeBalances;

use App\Filament\App\Resources\FeeBalances\Pages\ListFeeBalances;
use App\Filament\App\Resources\FeeBalances\Tables\FeeBalancesTable;
use App\Models\Student;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Who has paid, who has not."
 *
 * Reads the Student model but shows only the fee account, so the bursar
 * gets one screen answering: paid in full, part paid, nothing paid, and
 * how much is outstanding.
 *
 * Charged and paid are computed as correlated subqueries so the columns
 * can be sorted and filtered in the database rather than in PHP -- that
 * matters at 700+ students.
 */
class FeeBalanceResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Fees';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Fee Balances';

    protected static ?string $modelLabel = 'Fee balance';

    protected static ?string $pluralModelLabel = 'Fee Balances';

    protected static ?string $recordTitleAttribute = 'name';

    public static function table(Table $table): Table
    {
        return FeeBalancesTable::configure($table);
    }

    /**
     * SQL for "everything ever charged to this student, after discounts".
     */
    public static function chargedSql(): string
    {
        return '(select coalesce(sum(invoice_items.amount - invoice_items.discount_amount), 0)
                 from invoice_items
                 join invoices on invoice_items.invoice_id = invoices.id
                 where invoices.student_id = students.id)';
    }

    /**
     * SQL for "everything ever paid by this student".
     */
    public static function paidSql(): string
    {
        return '(select coalesce(sum(student_payments.amount), 0)
                 from student_payments
                 where student_payments.student_id = students.id)';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['schoolClass', 'section'])
            ->select('students.*')
            ->selectRaw(static::chargedSql() . ' as total_charged')
            ->selectRaw(static::paidSql() . ' as total_paid')
            ->selectRaw('(' . static::chargedSql() . ' - ' . static::paidSql() . ') as balance_owing');

        $user = auth()->user();

        if ($user && ! $user->hasRole('Super Admin')) {
            $query->where('students.school_id', $user->school_id);
        }

        return $query;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeeBalances::route('/'),
        ];
    }
}
