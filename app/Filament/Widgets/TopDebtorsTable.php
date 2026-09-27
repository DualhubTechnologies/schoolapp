<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\StudentAccount;
use App\Filament\Widgets\Concerns\SchoolScoped;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The students who owe the most, across all terms — who the bursar
 * should be chasing first.
 */
class TopDebtorsTable extends TableWidget
{
    use SchoolScoped;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Largest outstanding balances';

    /** Charged (after discounts) and paid, as correlated subqueries. */
    protected const CHARGED = '(select coalesce(sum(amount - discount_amount), 0) from student_charges where student_charges.student_id = students.id)';

    protected const PAID = '(select coalesce(sum(amount), 0) from student_payments where student_payments.student_id = students.id and student_payments.voided_at is null)';

    public static function canView(): bool
    {
        return static::userHandlesFees();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Student::query()
                    ->where('school_id', $this->schoolId())
                    ->where('status', 'active')
                    ->select('students.*')
                    ->selectRaw(self::CHARGED.' as charged_total')
                    ->selectRaw(self::PAID.' as paid_total')
                    ->whereRaw(self::CHARGED.' > '.self::PAID)
                    ->with(['schoolClass', 'section'])
            )
            ->defaultSort(fn ($query) => $query->orderByRaw('('.self::CHARGED.' - '.self::PAID.') desc'))
            ->columns([
                TextColumn::make('name')
                    ->label('Student')
                    ->description(fn (Student $record) => $record->admission_no)
                    ->searchable(['name', 'admission_no']),
                TextColumn::make('schoolClass.name')
                    ->label('Class')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, Student $record) => $record->section
                        ? "{$state} · {$record->section->name}"
                        : (string) $state),
                TextColumn::make('charged_total')
                    ->label('Charged')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('paid_total')
                    ->label('Paid')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('balance')
                    ->label('Balance (UGX)')
                    ->state(fn (Student $record) => (float) $record->charged_total - (float) $record->paid_total)
                    ->numeric()
                    ->weight('bold')
                    ->color('danger')
                    ->alignEnd(),
                TextColumn::make('paid_percent')
                    ->label('Paid')
                    ->state(fn (Student $record) => (float) $record->charged_total > 0
                        ? round((float) $record->paid_total / (float) $record->charged_total * 100).'%'
                        : '—')
                    ->badge()
                    ->color(fn (string $state) => match (true) {
                        (int) $state >= 75 => 'success',
                        (int) $state >= 40 => 'warning',
                        default => 'danger',
                    })
                    ->alignEnd(),
            ])
            ->recordActions([
                Action::make('statement')
                    ->label('Account')
                    ->icon('heroicon-o-book-open')
                    ->url(fn (Student $record) => StudentAccount::getUrl(['student' => $record->getKey()])),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('No outstanding balances')
            ->emptyStateDescription('Every student is fully paid up.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
