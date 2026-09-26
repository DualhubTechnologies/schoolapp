<?php

namespace App\Support;

use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Live feedback on email fields. While typing, the browser checks the
 * shape of the address and suggests fixes for common domain typos
 * (gmial.com → gmail.com). When the person leaves the field, the server
 * validates just that field, so "already registered" and similar errors
 * show before the form is saved.
 */
class EmailCheck
{
    /** Domains worth suggesting when someone mistypes one. */
    public const COMMON_DOMAINS = [
        'gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'icloud.com',
        'live.com', 'ymail.com', 'aol.com', 'protonmail.com',
    ];

    public static function apply(TextInput $field): TextInput
    {
        return $field
            ->email()
            // Production also checks the domain can receive mail, which
            // catches typos like "gmial.com". Skipped locally, where the
            // PC may be offline.
            ->rule(fn (): Closure => static::domainReceivesMail(...), app()->isProduction())
            ->autocomplete('email')
            ->live(onBlur: true)
            ->afterStateUpdated(function (TextInput $component, HasSchemas $livewire, ?string $state): void {
                // Tabbing past an empty field shouldn't flag it; "required" is checked on save.
                if (blank($state)) {
                    return;
                }

                // Pass the field's own messages and label, as a full save does,
                // so custom wording like "already registered" shows here too.
                $messages = [];
                $attributes = [];
                $component->dehydrateValidationMessages($messages);
                $component->dehydrateValidationAttributes($attributes);

                $livewire->validateOnly($component->getStatePath(), messages: $messages, attributes: $attributes);
            })
            ->belowContent(fn (TextInput $component) => view('filament.forms.email-check', [
                'statePath' => $component->getStatePath(),
                'domains' => static::COMMON_DOMAINS,
            ]));
    }

    /**
     * Validation rule: the part after the @ must be a domain that exists.
     * Badly formed addresses are left to the standard "email" rule.
     */
    public static function domainReceivesMail(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || Validator::make(['email' => $value], ['email' => 'email'])->fails()) {
            return;
        }

        if (Validator::make(['email' => $value], ['email' => 'email:dns'])->fails()) {
            $domain = Str::afterLast($value, '@');

            $fail("We can't find \"{$domain}\". Check the spelling after the @.");
        }
    }
}
