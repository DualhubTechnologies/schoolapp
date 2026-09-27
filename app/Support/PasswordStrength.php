<?php

namespace App\Support;

use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * One place for the password rules and the live feedback shown while
 * someone types. The browser checks the same requirements the server
 * enforces on save (Password::defaults() in AppServiceProvider is built
 * from rule() below), so the checklist never promises something the
 * server then rejects.
 */
class PasswordStrength
{
    public const MIN_LENGTH = 6;

    /** Production adds the letter, number and data-leak checks; local and testing only need the length. */
    public static function strict(): bool
    {
        return app()->isProduction();
    }

    public static function rule(): Password
    {
        return static::strict()
            ? Password::min(static::MIN_LENGTH)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->uncompromised()
            : Password::min(static::MIN_LENGTH);
    }

    /**
     * What the browser checks: a label and a JavaScript regex source (run
     * with the "u" flag). The patterns mirror Laravel's Password rule.
     *
     * @return list<array{label: string, pattern: string}>
     */
    public static function requirements(): array
    {
        if (! static::strict()) {
            return [['label' => 'At least '.static::MIN_LENGTH.' characters', 'pattern' => '^[\\s\\S]{'.static::MIN_LENGTH.',}$']];
        }

        return [
            ['label' => 'At least '.static::MIN_LENGTH.' characters', 'pattern' => '^[\\s\\S]{'.static::MIN_LENGTH.',}$'],
            ['label' => 'An uppercase letter', 'pattern' => '\\p{Lu}'],
            ['label' => 'A lowercase letter', 'pattern' => '\\p{Ll}'],
            ['label' => 'A number', 'pattern' => '\\p{N}'],
        ];
    }

    /** Adds the live strength checklist under a password field. */
    public static function meter(TextInput $field): TextInput
    {
        return $field
            ->autocomplete('new-password')
            ->belowContent(fn (TextInput $component) => view('filament.forms.password-strength', [
                'statePath' => $component->getStatePath(),
                'requirements' => static::requirements(),
                'breachCheck' => static::strict(),
            ]));
    }

    /**
     * Adds a live "passwords match" line under a confirmation field.
     * $passwordField is the name of the sibling password field.
     */
    public static function matches(TextInput $field, string $passwordField = 'password'): TextInput
    {
        return $field
            ->autocomplete('new-password')
            ->belowContent(fn (TextInput $component) => view('filament.forms.password-match', [
                'statePath' => $component->getStatePath(),
                'passwordStatePath' => Str::contains($component->getStatePath(), '.')
                    ? Str::beforeLast($component->getStatePath(), '.').'.'.$passwordField
                    : $passwordField,
            ]));
    }
}
