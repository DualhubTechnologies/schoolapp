<?php

namespace App\Filament\Pages\Auth;

use App\Support\PasswordStrength;
use Filament\Auth\Pages\PasswordReset\ResetPassword as BaseResetPassword;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Filament's "choose a new password" page, in the SchoolHub sign-in
 * design, with the same live strength checklist and match check as
 * registration.
 */
class ResetPassword extends BaseResetPassword
{
    protected static string $layout = 'filament.auth.layout';

    protected string $view = 'filament.auth.password';

    public function getHeading(): string|Htmlable|null
    {
        return 'Choose a new password';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'At least '.PasswordStrength::MIN_LENGTH.' characters. You will sign in with it from now on.';
    }

    protected function getPasswordFormComponent(): Component
    {
        /** @var TextInput $field */
        $field = parent::getPasswordFormComponent();

        return PasswordStrength::meter($field);
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        /** @var TextInput $field */
        $field = parent::getPasswordConfirmationFormComponent();

        return PasswordStrength::matches($field);
    }
}
