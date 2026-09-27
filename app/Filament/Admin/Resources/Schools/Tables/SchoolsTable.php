<?php

namespace App\Filament\Admin\Resources\Schools\Tables;

use App\Filament\Admin\Resources\Schools\SubscriptionActions;
use App\Models\School;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use App\Support\EmailVerificationCode;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Throwable;

class SchoolsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')
                    ->label('')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name='
                        .urlencode($record->name).'&background=random'),

                TextColumn::make('name')
                    ->label('School')
                    ->weight('bold')
                    ->description(fn (School $record): ?string => $record->unique_code)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('school_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => School::TYPES[$state] ?? 'Not set')
                    ->color(fn (?string $state): string => match ($state) {
                        'secondary' => 'info',
                        'primary' => 'success',
                        default => 'warning',
                    }),

                TextColumn::make('plan')
                    ->label('Plan')
                    ->state(fn (School $record) => SubscriptionManager::current($record)?->plan?->name ?? '—')
                    ->description(function (School $record) {
                        $st = SubscriptionManager::status($record);

                        return $st['ends_on'] ? 'to '.$st['ends_on']->format('j M Y') : null;
                    }),

                TextColumn::make('subscription_state')
                    ->label('Subscription')
                    ->badge()
                    ->state(fn (School $record) => SubscriptionManager::status($record)['state'])
                    ->formatStateUsing(fn (string $state) => SubscriptionActions::stateLabel($state))
                    ->color(fn (string $state) => SubscriptionActions::stateColor($state)),

                TextColumn::make('usage_students')
                    ->label('Students')
                    ->state(function (School $record) {
                        $u = SubscriptionManager::usage($record)['students'];

                        return number_format($u['used']).' / '.($u['limit'] === null ? '∞' : number_format($u['limit']));
                    })
                    ->color(function (School $record) {
                        $u = SubscriptionManager::usage($record)['students'];

                        return $u['limit'] !== null && $u['used'] >= $u['limit'] ? 'danger' : null;
                    }),

                TextColumn::make('usage_users')
                    ->label('Logins')
                    ->state(function (School $record) {
                        $u = SubscriptionManager::usage($record)['users'];

                        return $u['used'].' / '.($u['limit'] === null ? '∞' : $u['limit']);
                    }),

                TextColumn::make('city')
                    ->label('City / District')
                    ->description(fn (School $record): ?string => $record->country)
                    ->searchable(),

                TextColumn::make('phone')
                    ->label('Telephone')
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'inactive' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('currency')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('timezone')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('address')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('school_type')
                    ->label('Type')
                    ->options(School::TYPES),

                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'inactive' => 'Inactive',
                    ]),
            ])
            ->recordActions([
                SubscriptionActions::renew()->iconButton()->tooltip('Record payment'),
                SubscriptionActions::activationCode()->iconButton()->tooltip('Generate activation code'),
                ActionGroup::make([
                    EditAction::make(),
                    SubscriptionActions::changePlan(),
                    SubscriptionActions::extend(),
                    SubscriptionActions::suspend(),
                    self::confirmAdminEmail(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * For support: confirm a new school's administrator by hand when the
     * code email never reaches them (full mailbox, strict spam filter).
     */
    public static function confirmAdminEmail(): Action
    {
        return Action::make('confirmAdminEmail')
            ->label("Confirm admin's email")
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (School $record): bool => $record->users()
                ->whereNull('email_verified_at')
                ->whereNotNull('email_verification_code')
                ->exists())
            ->requiresConfirmation()
            ->modalHeading("Confirm the administrator's email")
            ->modalDescription('Only do this after checking, by phone or WhatsApp, that the address belongs to the person who registered the school. They can then sign in without the emailed code.')
            ->action(function (School $record): void {
                // Only the platform owner sees this, so if anything on the
                // server goes wrong, say what, rather than a blank error.
                try {
                    $confirmed = $record->users()
                        ->whereNull('email_verified_at')
                        ->whereNotNull('email_verification_code')
                        ->get()
                        ->each(fn (User $user) => EmailVerificationCode::markVerified($user))
                        ->count();
                } catch (Throwable $e) {
                    report($e);

                    Notification::make()
                        ->title('Could not confirm the email')
                        ->body(class_basename($e).': '.Str::limit($e->getMessage(), 300))
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title($confirmed ? 'Email confirmed — they can sign in now' : 'Already confirmed — they can sign in')
                    ->success()
                    ->send();
            });
    }
}
