<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\SchoolScoped;
use App\Models\StudentPayment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The latest fee payments recorded, newest first.
 */
class RecentPaymentsTable extends TableWidget
{
    use SchoolScoped;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent payments';

    public static function canView(): bool
    {
        return static::userHandlesFees();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StudentPayment::query()
                    ->where('school_id', $this->schoolId())
                    ->with(['student.schoolClass'])
            )
            ->defaultSort(fn ($query) => $query->orderByDesc('paid_on')->orderByDesc('id'))
            ->columns([
                TextColumn::make('receipt_no')
                    ->label('Receipt')
                    ->weight('semibold')
                    ->url(fn (StudentPayment $record) => \App\Filament\Pages\ReceivePayment::receiptUrl($record), shouldOpenInNewTab: true),
                TextColumn::make('paid_on')
                    ->label('Date')
                    ->date('j M Y'),
                TextColumn::make('student.name')
                    ->label('Student')
                    ->description(fn (StudentPayment $record) => $record->student?->admission_no)
                    ->searchable(),
                TextColumn::make('student.schoolClass.name')
                    ->label('Class')
                    ->badge(),
                TextColumn::make('method')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => StudentPayment::METHODS[$state] ?? $state)
                    ->color('gray'),
                TextColumn::make('reference')
                    ->label('Receipt / ref')
                    ->placeholder('—'),
                TextColumn::make('amount')
                    ->label('Amount (UGX)')
                    ->numeric()
                    ->weight('bold')
                    ->color('success')
                    ->alignEnd(),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('No payments recorded yet')
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
