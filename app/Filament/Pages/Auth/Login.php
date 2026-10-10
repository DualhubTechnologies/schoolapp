<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Services\SmsSender;
use App\Support\EmailVerificationCode;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Hash;
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

    /**
     * Email address or phone number: parents usually sign in with their
     * phone and the PIN the school gave them (App\Services\ParentLogins).
     */
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email or phone number')
            ->placeholder('you@school.ac.ug or 0772 123456')
            ->prefixIcon(Heroicon::OutlinedUser)
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    /**
     * A phone number is turned into the email of the account it belongs
     * to, so the usual password check (and rate limit) applies. Several
     * accounts may share a phone (a parent at two schools): the one whose
     * password matches is used.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $login = trim((string) $data['email']);
        $phone = str_contains($login, '@') ? null : SmsSender::normalisePhone($login);

        if ($phone !== null) {
            $match = User::where('phone', $phone)
                ->get()
                ->first(fn (User $user): bool => Hash::check((string) $data['password'], $user->password));

            $login = $match instanceof User ? $match->email : $login;
        }

        return [
            'email' => $login,
            'password' => $data['password'],
        ];
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
