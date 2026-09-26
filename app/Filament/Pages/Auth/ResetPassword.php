<?php

namespace App\Filament\Pages\Auth;

use App\Support\PasswordStrength;
use Filament\Auth\Pages\PasswordReset\ResetPassword as BaseResetPassword;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

/**
 * Filament's "choose a new password" page, with the same live strength
 * checklist and match check as registration.
 */
class ResetPassword extends BaseResetPassword
{
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
