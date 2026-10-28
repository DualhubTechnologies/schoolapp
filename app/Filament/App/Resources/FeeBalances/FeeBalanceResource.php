<?php

namespace App\Filament\App\Resources\FeeBalances;

use App\Filament\App\Resources\FeeBalances\Pages\ListFeeBalances;
use App\Filament\App\Resources\FeeBalances\Tables\FeeBalancesTable;
use App\Filament\App\Resources\Students\StudentResource;
use App\Filament\Concerns\GatedByModule;
use App\Filament\Concerns\PlacedInMenu;
use App\Filament\Pages\ReceivePayment;
use App\Filament\Pages\StudentAccount;
use App\Models\Student;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * "Who has paid, who has not."
 *
 * Charged and paid are correlated subqueries so the columns can be sorted
 * and filtered in the database rather than in PHP - which matters once a
 * school has hundreds of students.
 */
class FeeBalanceResource extends Resource
{
    use GatedByModule;
    use PlacedInMenu;

    /**
     * A learner's account is reached from their Learners search result
     * ("Fees account"). Only those who cannot open Learners (a bursar)
     * find learners here instead, straight to their account.
     */
    public static function canGloballySearch(): bool
    {
        return parent::canGloballySearch() && ! StudentResource::canAccess();
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'admission_no', 'schoolpay_code'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return StudentResource::getRecordTitle($record) ?? '';
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        if (! $record instanceof Student) {
            return [];
        }

        return array_filter([
            'Class' => $record->schoolClass?->name,
            'Balance' => 'UGX '.number_format((float) $record->getAttribute('balance_owing')),
        ]);
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return StudentAccount::getUrl(['student' => $record->getKey()]);
    }

    /** @return array<Action> */
    public static function getGlobalSearchResultActions(Model $record): array
    {
        return [Action::make('pay')->label('Receive payment')->url(ReceivePayment::getUrl(['student' => $record->getKey()]))];
    }

    protected static ?string $model = Student::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static ?string $modelLabel = 'student account';

    protected static ?string $pluralModelLabel = 'Student Accounts';

    protected static ?string $recordTitleAttribute = 'name';

    public static function table(Table $table): Table
    {
        return FeeBalancesTable::configure($table);
    }

    /**
     * Everything ever charged to this student, after discounts.
     *
     * @return literal-string
     */
    public static function chargedSql(): string
    {
        return '(select coalesce(sum(student_charges.amount - student_charges.discount_amount), 0)
                 from student_charges
                 where student_charges.student_id = students.id)';
    }

    /**
     * Everything ever paid by this student.
     *
     * @return literal-string
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
