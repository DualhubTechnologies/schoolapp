<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Filament's "forgot password" page, in the SchoolHub sign-in design.
 */
class RequestPasswordReset extends BaseRequestPasswordReset
{
    protected static string $layout = 'filament.auth.layout';

    protected string $view = 'filament.auth.password';

    public function getHeading(): string|Htmlable|null
    {
        return 'Forgot your password?';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Type the email address you sign in with, and we will send you a link to choose a new password.';
    }
}
