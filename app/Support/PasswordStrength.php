<?php

namespace App\Support;

use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * One place for the password rule and the live feedback shown while
 * someone types. Only the minimum length is enforced (Password::defaults()
 * in AppServiceProvider is built from rule() below); the letter and
 * number items are advice, so a person who insists on a weak password
 * can still save it.
 */
class PasswordStrength
{
    public const MIN_LENGTH = 6;

    public static function rule(): Password
    {
        return Password::min(static::MIN_LENGTH);
    }

    /**
     * What the browser checks: a label and a JavaScript regex source (run
     * with the "u" flag). The patterns mirror Laravel's Password rule.
     *
     * @return list<array{label: string, pattern: string}>
     */
    public static function requirements(): array
    {
        return [
            ['label' => 'At least '.static::MIN_LENGTH.' characters', 'pattern' => '^[\\s\\S]{'.static::MIN_LENGTH.',}$'],
            ['label' => 'A letter', 'pattern' => '\\p{L}'],
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
                'minLength' => static::MIN_LENGTH,
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
