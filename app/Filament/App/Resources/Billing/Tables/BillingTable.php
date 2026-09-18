<?php

namespace App\Filament\App\Resources\Billing\Tables;

use App\Filament\Pages\StudentAccount;
use App\Models\Term;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BillingTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('charged_on')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('student.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('student.admission_no')
                    ->label('Adm. No.')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('student.schoolClass.name')
                    ->label('Class')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('student.residencyType.name')
                    ->label('Residency')
                    ->badge()
                    ->color('warning')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('description')
                    ->label('Charge')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('term.name')
                    ->label('Term')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('amount')
                    ->money('UGX')
                    ->sortable(),

                TextColumn::make('discount_amount')
                    ->label('Discount')
                    ->money('UGX')
                    ->color('success')
                    ->placeholder('—')
                    ->description(fn ($record) => $record->discount_reason)
                    ->toggleable(),

                TextColumn::make('net')
                    ->label('Net')
                    ->state(fn ($record) => $record->netAmount())
                    ->money('UGX')
                    ->weight('bold'),
            ])
            ->defaultSort('charged_on', 'desc')
            ->filters([
                SelectFilter::make('term_id')
                    ->label('Term')
                    ->options(fn (): array => Term::query()
                        ->where('school_id', auth()->user()?->school_id)
                        ->with('academicYear')
                        ->get()
                        ->mapWithKeys(fn (Term $t) => [$t->id => $t->label()])
                        ->toArray())
                    ->default(fn () => Term::current()?->getKey()),

                SelectFilter::make('school_class')
                    ->label('Class')
                    ->relationship('student.schoolClass', 'name')
                    ->preload(),

                SelectFilter::make('residency')
                    ->label('Residency')
                    ->relationship('student.residencyType', 'name')
                    ->preload(),
            ])
            ->recordActions([
                Action::make('account')
                    ->label('Account')
                    ->icon('heroicon-o-book-open')
                    ->color('primary')
                    ->url(fn ($record): string => StudentAccount::getUrl() . '?student=' . $record->student_id),

                // Deleting a charge reduces what the student was charged.
                // That is how a mistaken billing run is corrected, so it is
                // available -- but it moves the balance, hence the warning.
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('This removes the charge from the student\'s account and reduces their balance.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalDescription('This removes the charges and reduces those students\' balances.'),
                ]),
            ])
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
