<?php

namespace App\Filament\App\Resources\Staff\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ArrearsRelationManager extends RelationManager
{
    protected static string $relationship = 'arrears';

    protected static ?string $title = 'Arrears';

    protected static ?string $modelLabel = 'salary arrear';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('amount')
                    ->label('Arrears amount')
                    ->required()
                    ->numeric()
                    ->prefix('UGX'),
                Textarea::make('reason')
                    ->required()
                    ->placeholder('e.g. Salary adjustment backdated to July, Missed overtime payment'),
                Select::make('month')
                    ->label('Arrears for month')
                    ->options([
                        1 => 'January', 2 => 'February', 3 => 'March',
                        4 => 'April', 5 => 'May', 6 => 'June',
                        7 => 'July', 8 => 'August', 9 => 'September',
                        10 => 'October', 11 => 'November', 12 => 'December',
                    ])
                    ->required(),
                TextInput::make('year')
                    ->label('Arrears for year')
                    ->required()
                    ->numeric()
                    ->default(now()->year)
                    ->minValue(2020)
                    ->maxValue(2050),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('month')
                    ->label('For period')
                    ->formatStateUsing(function ($record) {
                        $months = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
                        return ($months[$record->month] ?? 'Unknown') . ' ' . $record->year;
                    })
                    ->sortable(),
                TextColumn::make('amount')
                    ->money('UGX')
                    ->sortable(),
                TextColumn::make('reason')
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'approved' => 'warning',
                        'paid' => 'success',
                    }),
                TextColumn::make('appliedInPeriod.month')
                    ->label('Paid in')
                    ->formatStateUsing(function ($record) {
                        if (! $record->applied_in_period_id) return null;
                        $months = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
                        return ($months[$record->appliedInPeriod->month] ?? '') . ' ' . $record->appliedInPeriod->year;
                    })
                    ->placeholder('Not yet applied'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'paid' => 'Paid',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['school_id'] = $this->getOwnerRecord()->school_id;
                        $data['status'] = 'pending';
                        return $data;
                    }),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => 'approved']);

                        Notification::make()
                            ->title('Arrears approved — will be included in next payroll generation')
                            ->success()
                            ->send();
                    }),
                EditAction::make()
                    ->visible(fn ($record) => $record->status === 'pending'),
                DeleteAction::make()
                    ->visible(fn ($record) => $record->status === 'pending'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}