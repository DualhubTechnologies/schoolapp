<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Support\EmailVerificationCode;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * The sign-in page, in SchoolHub's own split-screen design.
 *
 * A school administrator who has not yet confirmed their email with the
 * code we sent (App\Support\EmailVerificationCode) is not signed in: after
 * a correct password they are taken to the page to enter the code.
 */
class Login extends BaseLogin
{
    protected static string $layout = 'filament.auth.layout';

    /** Set when the password was right but the email is not yet confirmed. */
    protected ?User $unconfirmedUser = null;

    protected string $view = 'filament.auth.login';

    public function getTitle(): string
    {
        return 'Sign in';
    }

    protected function getEmailFormComponent(): Component
    {
        /** @var TextInput $field */
        $field = parent::getEmailFormComponent();

        return $field
            ->placeholder('you@school.ac.ug')
            ->prefixIcon(Heroicon::OutlinedEnvelope);
    }

    protected function getPasswordFormComponent(): Component
    {
        /** @var TextInput $field */
        $field = parent::getPasswordFormComponent();

        return $field
            ->placeholder('Your password')
            ->prefixIcon(Heroicon::OutlinedLockClosed)
            ->revealable();
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            return parent::authenticate();
        } catch (ValidationException $exception) {
            if (! $this->unconfirmedUser) {
                throw $exception;
            }
        }

        $user = $this->unconfirmedUser;
        EmailVerificationCode::rememberFor($user);

        // The last code has run out: send a fresh one to type in.
        $sent = ! EmailVerificationCode::hasExpired($user) || EmailVerificationCode::send($user);

        Notification::make()
            ->title('Confirm your email to continue')
            ->body($sent
                ? 'Enter the 6-digit code we emailed to '.EmailVerificationCode::maskEmail($user->email).'.'
                : 'We could not send the code just now. Wait a minute, then press "Send a new code".')
            ->warning()
            ->persistent()
            ->send();

        $this->redirect(VerifyEmail::url());

        return null;
    }

    /**
     * Runs only once the password has been checked, so an unconfirmed
     * account is revealed only to someone who knows its password.
     */
    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        if ($user instanceof User && EmailVerificationCode::isPending($user)) {
            $this->unconfirmedUser = $user;

            return false;
        }

        return parent::isUserAllowedToAccessPanel($user);
    }

    /**
     * An unconfirmed email is not a failed sign-in: keep it out of the
     * failed-login log.
     */
    protected function fireFailedEvent(Guard $guard, ?Authenticatable $user, #[SensitiveParameter] array $credentials): void
    {
        if ($this->unconfirmedUser) {
            return;
        }

        parent::fireFailedEvent($guard, $user, $credentials);
    }
}
