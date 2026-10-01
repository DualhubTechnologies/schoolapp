<?php

namespace App\Filament\Auth;

use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * Two-step sign-in with an authenticator app, where linking a phone first
 * needs the account password -- so someone who finds a signed-in screen
 * cannot tie the account to their own phone. The confirmation lasts ten
 * minutes. Turning it off or making new recovery codes already needs the
 * current code (or a recovery code), as Filament does by default.
 */
class ConfirmedAppAuthentication extends AppAuthentication
{
    public const SESSION_KEY = 'auth.app_authentication_password_confirmed_at';

    public const CONFIRMATION_SECONDS = 600;

    public static function passwordRecentlyConfirmed(): bool
    {
        return time() - (int) session(self::SESSION_KEY, 0) < self::CONFIRMATION_SECONDS;
    }

    /**
     * @return array<Action>
     */
    public function getActions(): array
    {
        $user = Filament::auth()->user();

        return collect(parent::getActions())
            ->map(fn (Action $action): Action => $action->getName() === 'setUpAppAuthentication'
                ? $action->hidden(fn (): bool => $this->isEnabled($user) || ! static::passwordRecentlyConfirmed())
                : $action)
            ->prepend(
                Action::make('confirmPasswordForAppAuthentication')
                    ->label('Confirm your password to set up')
                    ->icon('heroicon-o-lock-closed')
                    ->link()
                    ->hidden(fn (): bool => $this->isEnabled($user) || static::passwordRecentlyConfirmed())
                    ->modalHeading('Confirm it is you')
                    ->modalDescription('Enter your current password before linking an authenticator app to this account.')
                    ->schema([
                        TextInput::make('password')
                            ->label('Current password')
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->currentPassword(guard: Filament::getAuthGuard())
                            ->required()
                            ->dehydrated(false),
                    ])
                    ->modalSubmitActionLabel('Confirm')
                    ->action(function (): void {
                        session()->put(self::SESSION_KEY, time());

                        Notification::make()
                            ->title('Password confirmed')
                            ->body('Now press "Set up" to link your authenticator app.')
                            ->success()
                            ->send();
                    }),
            )
            ->values()
            ->all();
    }
}
