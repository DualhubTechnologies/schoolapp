<?php

namespace App\Filament\Admin\Resources\Schools\RelationManagers;

use App\Models\SubscriptionPayment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionPaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptionPayments';

    protected static ?string $title = 'Payments';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('paid_on', 'desc')
            ->columns([
                TextColumn::make('paid_on')->label('Date')->date('j M Y'),
                TextColumn::make('amount')->numeric()->prefix('UGX ')->weight('bold')
                    ->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()->label('Total')->numeric()->prefix('UGX ')),
                TextColumn::make('method')->formatStateUsing(fn ($state) => SubscriptionPayment::METHODS[$state] ?? $state),
                TextColumn::make('reference')->placeholder('—')->searchable(),
                TextColumn::make('subscription.plan.name')->label('For')
                    ->description(fn (SubscriptionPayment $record) => $record->subscription?->periodLabel()),
                TextColumn::make('received_by')->label('Recorded by')->toggleable(),
                TextColumn::make('notes')->limit(40)->toggleable(isToggledHiddenByDefault: true),
            ]);
    }
}
