<?php

namespace App\Filament\App\Resources\SalaryArrears\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class SalaryArrearsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('staff.name')
                    ->label('Staff member')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('staff.staff_no')
                    ->label('Staff No.')
                    ->searchable(),
                TextColumn::make('school.name')
                    ->label('School')
                    ->searchable(),
                TextColumn::make('month')
                    ->label('For period')
                    ->formatStateUsing(function ($record) {
                        $months = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];

                        return ($months[$record->month] ?? 'Unknown').' '.$record->year;
                    })
                    ->sortable(),
                TextColumn::make('amount')
                    ->money('UGX')
                    ->sortable(),
                TextColumn::make('reason')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'paid' => 'success',
                    }),
                TextColumn::make('appliedInPeriod.month')
                    ->label('Paid in')
                    ->formatStateUsing(function ($record) {
                        if (! $record->applied_in_period_id) {
                            return null;
                        }
                        $months = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];

                        return ($months[$record->appliedInPeriod->month] ?? '').' '.$record->appliedInPeriod->year;
                    })
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Detected on')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'paid' => 'Paid',
                    ])
                    ->default('pending'),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => 'approved']);

                        Notification::make()
                            ->title('Arrears approved for '.$record->staff->name)
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Reject arrears')
                    ->modalDescription(fn ($record) => "This will permanently delete the arrears record for {$record->staff->name}. This person will NOT receive back-pay for this period. Continue?")
                    ->action(function ($record) {
                        $name = $record->staff->name;
                        $record->delete();

                        Notification::make()
                            ->title("Arrears rejected and removed for {$name}")
                            ->warning()
                            ->send();
                    }),
                EditAction::make()
                    ->visible(fn ($record) => $record->status === 'pending'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveSelected')
                        ->label('Approve selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'pending') {
                                    $record->update(['status' => 'approved']);
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->title("{$count} arrears approved")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
