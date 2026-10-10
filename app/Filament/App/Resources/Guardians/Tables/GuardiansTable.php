<?php

namespace App\Filament\App\Resources\Guardians\Tables;

use App\Filament\App\Resources\Guardians\GuardianResource;
use App\Filament\Support\ConfirmWithPassword;
use App\Models\Guardian;
use App\Services\ParentLogins;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class GuardiansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No parents or guardians yet')
            ->emptyStateDescription('They are added with each learner, or here. Their phone numbers receive receipts and fee reminders.')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('relationship')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Guardian::RELATIONSHIPS[$state] ?? $state)
                    ->color(fn (?string $state): string => match ($state) {
                        'father' => 'info',
                        'mother' => 'warning',
                        'guardian' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('students_count')
                    ->label('Children')
                    ->counts('students')
                    ->badge(),
                TextColumn::make('user_id')
                    ->label('Parent login')
                    ->formatStateUsing(fn ($state): string => $state ? 'Yes' : 'No')
                    ->default(null)
                    ->placeholder('No')
                    ->badge()
                    ->color(fn ($state): string => $state ? 'success' : 'gray')
                    ->visible(fn (): bool => ParentLogins::planAllows()),
                TextColumn::make('occupation')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('school.name')
                    ->label('School')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('relationship')
                    ->options(Guardian::RELATIONSHIPS),
            ])
            ->recordActions([
                static::giveLoginAction(),
                static::newPinAction(),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    static::giveLoginsBulkAction(),
                    ConfirmWithPassword::on(DeleteBulkAction::make()),
                ]),
            ])
            ->paginationPageOptions([5, 10, 25, 50])
            ->defaultPaginationPageOption(5);
    }

    /** Who may hand out parent logins: the admissions office, on a plan that includes them. */
    public static function mayGiveLogins(): bool
    {
        return GuardianResource::canCreate() && ParentLogins::planAllows();
    }

    /** "Give login": the parent signs in with their phone and the PIN texted to them. */
    protected static function giveLoginAction(): Action
    {
        return Action::make('giveLogin')
            ->label('Give login')
            ->icon('heroicon-o-key')
            ->color('success')
            ->visible(fn (Guardian $record): bool => ! $record->hasLogin() && static::mayGiveLogins())
            ->modalHeading(fn (Guardian $record): string => 'Give '.$record->name.' a parent login')
            ->modalDescription(fn (Guardian $record): string => 'They sign in with their phone number ('.($record->phone ?: 'none recorded').') and a 6-digit PIN, and see only their own children: fees, receipts, report cards and attendance.')
            ->schema([
                Toggle::make('text')
                    ->label('Text the PIN to the parent')
                    ->default(true),
            ])
            ->modalSubmitActionLabel('Create login')
            ->action(function (Guardian $record, array $data): void {
                try {
                    $result = app(ParentLogins::class)->createFor($record, (bool) $data['text']);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title('No login created')->body($e->getMessage())->warning()->send();

                    return;
                }

                Notification::make()
                    ->title('Parent login ready for '.$record->name)
                    ->body(static::pinMessage($result['pin'], $result['texted'], $result['error'], $record))
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    /** "Send new PIN": for a parent who forgot theirs. */
    protected static function newPinAction(): Action
    {
        return Action::make('newPin')
            ->label('Send new PIN')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->visible(fn (Guardian $record): bool => $record->hasLogin() && static::mayGiveLogins())
            ->requiresConfirmation()
            ->modalHeading(fn (Guardian $record): string => 'Send '.$record->name.' a new PIN?')
            ->modalDescription('Their old PIN stops working. The new one is texted to them and shown to you.')
            ->action(function (Guardian $record): void {
                $result = app(ParentLogins::class)->resetPin($record);

                Notification::make()
                    ->title('New PIN for '.$record->name)
                    ->body(static::pinMessage($result['pin'], $result['texted'], $result['error'], $record))
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    /** Logins for several parents at once, each PIN texted to them. */
    protected static function giveLoginsBulkAction(): BulkAction
    {
        return BulkAction::make('giveLogins')
            ->label('Give parent logins')
            ->icon('heroicon-o-key')
            ->visible(fn (): bool => static::mayGiveLogins())
            ->requiresConfirmation()
            ->modalHeading('Give the selected parents logins?')
            ->modalDescription('Each parent gets a 6-digit PIN by SMS and signs in with their phone number. Parents who already have a login are left as they are.')
            ->modalSubmitActionLabel('Create logins')
            ->action(function (Collection $records): void {
                /** @var Collection<int, Guardian> $records */
                $logins = app(ParentLogins::class);
                $created = 0;
                $skipped = [];

                foreach ($records as $guardian) {
                    if ($guardian->hasLogin()) {
                        continue;
                    }

                    try {
                        $logins->createFor($guardian);
                        $created++;
                    } catch (InvalidArgumentException) {
                        $skipped[] = $guardian->name;
                    }
                }

                Notification::make()
                    ->title($created.' parent '.str('login')->plural($created).' created')
                    ->body($skipped ? 'No valid phone number for: '.implode(', ', $skipped).'.' : 'Each parent was texted their PIN.')
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    protected static function pinMessage(?string $pin, bool $texted, ?string $error, Guardian $guardian): string
    {
        if ($pin === null) {
            return 'They already had a login with this phone number, so this record now uses it.';
        }

        return "Phone: {$guardian->phone} · PIN: {$pin}. "
            .($texted ? 'The PIN was texted to them.' : ($error ? "The SMS was not sent ({$error}): give them the PIN yourself." : 'Give them the PIN yourself.'));
    }
}
