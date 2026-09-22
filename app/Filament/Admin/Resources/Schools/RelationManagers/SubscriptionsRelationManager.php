<?php

namespace App\Filament\Admin\Resources\Schools\RelationManagers;

use App\Models\Subscription;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    protected static ?string $title = 'Subscription periods';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('plan.name')->label('Plan')->weight('bold'),
                TextColumn::make('cycle')->badge()->formatStateUsing(fn ($state) => Subscription::CYCLES[$state] ?? $state)->color('gray'),
                TextColumn::make('starts_on')->label('From')->date('j M Y'),
                TextColumn::make('ends_on')->label('To')->date('j M Y'),
                TextColumn::make('amount')->label('Price')->numeric()->prefix('UGX '),
                TextColumn::make('paid')
                    ->label('Received')
                    ->state(fn (Subscription $record) => $record->payments()->sum('amount'))
                    ->numeric()
                    ->prefix('UGX '),
                TextColumn::make('is_cancelled')
                    ->label('')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Cancelled' : null)
                    ->color('danger'),
                TextColumn::make('created_by')->label('By')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')->limit(40)->toggleable(),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('For a period added by mistake. Payments stay on record.')
                    ->visible(fn (Subscription $record) => ! $record->is_cancelled)
                    ->action(fn (Subscription $record) => $record->update(['is_cancelled' => true])),
            ]);
    }
}
