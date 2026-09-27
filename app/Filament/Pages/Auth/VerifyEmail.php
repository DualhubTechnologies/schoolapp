<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Notifications\WelcomeToSchoolHub;
use App\Support\EmailVerificationCode;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\OneTimeCodeInput;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * "Check your email": the new school administrator types in the 6-digit
 * code we emailed (App\Support\EmailVerificationCode). Reached straight
 * after registration, or from the sign-in page when the email is not yet
 * confirmed. Once confirmed they sign in as usual.
 *
 * @property-read Schema $form
 */
class VerifyEmail extends SimplePage
{
    use WithRateLimiting;

    protected static string $layout = 'filament.auth.layout';

    protected string $view = 'filament.auth.verify-email';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public string $maskedEmail = '';

    public static function url(): string
    {
        return route('filament.app.auth.verify-email');
    }

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            $this->redirect(Filament::getUrl());

            return;
        }

        $user = EmailVerificationCode::pendingUser();

        if (! $user || ! EmailVerificationCode::isPending($user)) {
            EmailVerificationCode::forget();
            $this->redirect(Filament::getLoginUrl());

            return;
        }

        $this->maskedEmail = EmailVerificationCode::maskEmail($user->email);
        $this->form->fill();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Confirm your email';
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            OneTimeCodeInput::make('code')
                ->label('Confirmation code')
                ->hiddenLabel()
                ->length(EmailVerificationCode::LENGTH)
                ->required()
                ->autofocus()
                ->validationMessages([
                    'required' => 'Enter the 6-digit code from the email.',
                    'digits' => 'The code is 6 digits.',
                    'numeric' => 'The code is 6 digits.',
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('verify')
                ->footer([
                    Actions::make([
                        Action::make('verify')
                            ->label('Confirm email')
                            ->icon('heroicon-m-check-badge')
                            ->size('lg')
                            ->submit('verify'),
                    ])->fullWidth()->key('form-actions'),
                ]),
        ]);
    }

    public function verify(): void
    {
        try {
            $this->rateLimit(10);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title("Too many tries. Wait {$exception->secondsUntilAvailable} seconds and try again.")
                ->danger()
                ->send();

            return;
        }

        $user = $this->pendingUserOrLeave();

        if (! $user) {
            return;
        }

        $code = (string) ($this->form->getState()['code'] ?? '');

        $result = EmailVerificationCode::attempt($user, $code);

        if ($result !== EmailVerificationCode::VERIFIED) {
            $this->form->fill();

            throw ValidationException::withMessages(['data.code' => match ($result) {
                EmailVerificationCode::EXPIRED => 'This code has expired. Press "Send a new code" below.',
                EmailVerificationCode::TOO_MANY_ATTEMPTS => 'Too many wrong codes. Press "Send a new code" below.',
                default => $this->wrongCodeMessage($user),
            }]);
        }

        EmailVerificationCode::forget();
        $this->sendWelcome($user);

        Notification::make()
            ->title('Email confirmed')
            ->body('Thank you. Sign in with your email and password to set up your school.')
            ->success()
            ->persistent()
            ->send();

        $this->redirect(Filament::getLoginUrl());
    }

    public function resendAction(): Action
    {
        return Action::make('resend')
            ->label('Send a new code')
            ->link()
            ->action(function (): void {
                $user = $this->pendingUserOrLeave();

                if (! $user) {
                    return;
                }

                $wait = EmailVerificationCode::secondsUntilResend($user);

                if ($wait > 0) {
                    Notification::make()
                        ->title("You can ask for a new code in {$wait} seconds.")
                        ->warning()
                        ->send();

                    return;
                }

                $this->form->fill();

                if (! EmailVerificationCode::send($user)) {
                    static::notifyNotSent();

                    return;
                }

                Notification::make()
                    ->title('New code sent')
                    ->body("Check {$this->maskedEmail}. Codes can take a minute to arrive — look in Spam or Promotions too.")
                    ->success()
                    ->send();
            });
    }

    /**
     * Shown by the registration page when the first email could not be sent.
     */
    public static function notifyNotSent(): void
    {
        Notification::make()
            ->title('We could not send the email just now')
            ->body('Please wait a minute and press "Send a new code". If it keeps failing, WhatsApp us and we will help.')
            ->danger()
            ->persistent()
            ->send();
    }

    protected function pendingUserOrLeave(): ?User
    {
        $user = EmailVerificationCode::pendingUser();

        if ($user && EmailVerificationCode::isPending($user)) {
            return $user;
        }

        EmailVerificationCode::forget();
        $this->redirect(Filament::getLoginUrl());

        return null;
    }

    protected function wrongCodeMessage(User $user): string
    {
        $left = EmailVerificationCode::attemptsLeft($user);

        return $left > 0
            ? "That code is not right. Check the latest email and try again ({$left} ".str('try')->plural($left).' left).'
            : 'Too many wrong codes. Press "Send a new code" below.';
    }

    /**
     * The welcome email (trial dates, how to start) follows confirmation.
     * A courtesy: a mail failure must not stop the sign-up.
     */
    protected function sendWelcome(User $user): void
    {
        $school = $user->school;
        $trialEndsOn = $school?->subscriptions()->latest('ends_on')->value('ends_on');

        if (! $school || ! $trialEndsOn) {
            return;
        }

        try {
            $user->notify(new WelcomeToSchoolHub($school, Carbon::parse($trialEndsOn)));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
