<?php

namespace App\Filament\App\Resources\FeeBalances;

use App\Filament\App\Resources\FeeBalances\Pages\ListFeeBalances;
use App\Filament\App\Resources\FeeBalances\Tables\FeeBalancesTable;
use App\Filament\Concerns\GatedByModule;
use App\Models\Student;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Who has paid, who has not."
 *
 * Charged and paid are correlated subqueries so the columns can be sorted
 * and filtered in the database rather than in PHP — which matters once a
 * school has hundreds of students.
 */
class FeeBalanceResource extends Resource
{
    use GatedByModule;

    protected static ?string $model = Student::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|\UnitEnum|null $navigationGroup = 'Fees';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Student Accounts';

    protected static ?string $modelLabel = 'student account';

    protected static ?string $pluralModelLabel = 'Student Accounts';

    protected static ?string $recordTitleAttribute = 'name';

    public static function table(Table $table): Table
    {
        return FeeBalancesTable::configure($table);
    }

    /**
     * Everything ever charged to this student, after discounts.
     */
    public static function chargedSql(): string
    {
        return '(select coalesce(sum(student_charges.amount - student_charges.discount_amount), 0)
                 from student_charges
                 where student_charges.student_id = students.id)';
    }

    /**
     * Everything ever paid by this student.
     */
    public static function paidSql(): string
    {
        return '(select coalesce(sum(student_payments.amount), 0)
                 from student_payments
                 where student_payments.student_id = students.id
                   and student_payments.voided_at is null)';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['schoolClass', 'section', 'residencyType'])
            ->select('students.*')
            ->selectRaw(static::chargedSql().' as total_charged')
            ->selectRaw(static::paidSql().' as total_paid')
            ->selectRaw('('.static::chargedSql().' - '.static::paidSql().') as balance_owing');

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
