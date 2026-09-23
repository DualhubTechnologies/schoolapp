<?php

namespace App\Filament\Admin\Resources\Schools\RelationManagers;

use App\Models\SubscriptionActivationCode;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ActivationCodesRelationManager extends RelationManager
{
    protected static string $relationship = 'activationCodes';

    protected static ?string $title = 'Activation codes';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')->fontFamily('mono')->copyable(),
                TextColumn::make('plan.name'),
                TextColumn::make('amount')->label('Price')->numeric()->prefix('UGX '),
                TextColumn::make('status')
                    ->state(fn (SubscriptionActivationCode $record) => ucfirst($record->status()))
                    ->badge()
                    ->color(fn (SubscriptionActivationCode $record) => match ($record->status()) {
                        'active' => 'success',
                        'used' => 'gray',
                        'expired', 'revoked' => 'danger',
                    }),
                TextColumn::make('expires_at')->date('j M Y'),
                TextColumn::make('used_at')->label('Used')->dateTime('j M Y, g:ia')->placeholder('Not yet'),
                TextColumn::make('created_by')->label('Issued by')->toggleable(),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (SubscriptionActivationCode $record) => $record->status() === 'active')
                    ->action(function (SubscriptionActivationCode $record): void {
                        $record->update(['revoked_at' => now()]);
                        Notification::make()->title('Code revoked')->success()->send();
                    }),
            ]);
    }
}
