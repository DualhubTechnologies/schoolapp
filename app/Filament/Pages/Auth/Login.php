<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;

/**
 * The sign-in page, in SchoolHub's own split-screen design.
 */
class Login extends BaseLogin
{
    protected static string $layout = 'filament.auth.layout';

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
}
