<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;

/**
 * Deleting sensitive records (schools, students, staff, user logins,
 * guardians, payroll) asks for the person's own password in the delete
 * window, so a signed-in computer left unattended cannot be used to wipe
 * them. A wrong password stops the delete with a message on the field.
 */
class ConfirmWithPassword
{
    /**
     * @template TAction of Action
     *
     * @param  TAction  $action
     * @return TAction
     */
    public static function on(Action $action): Action
    {
        return $action->schema([
            TextInput::make('password')
                ->label('Your password')
                ->helperText('This cannot be undone. Enter your password to confirm.')
                ->password()
                ->revealable(filament()->arePasswordsRevealable())
                ->currentPassword(guard: Filament::getAuthGuard())
                ->required()
                ->dehydrated(false),
        ]);
    }
}
