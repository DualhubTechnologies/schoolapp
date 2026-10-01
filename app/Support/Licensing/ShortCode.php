<?php

namespace App\Support\Licensing;

/**
 * Licence codes as schools type them: FGDH-FWFH-2342-WETR. Sixteen
 * characters from 32 that cannot be mistaken for one another (no O/0 or
 * I/1), so about 80 random bits: far too many to guess, even without the
 * rate limit on activation.
 */
final class ShortCode
{
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const LENGTH = 16;

    public static function generate(): string
    {
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return self::format($code);
    }

    /** "fgdh fwfh 2342 wetr", "FGDHFWFH2342WETR" -> "FGDH-FWFH-2342-WETR"; null if it cannot be one. */
    public static function normalise(string $typed): ?string
    {
        $plain = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $typed));

        if (strlen($plain) !== self::LENGTH || strspn($plain, self::ALPHABET) !== self::LENGTH) {
            return null;
        }

        return self::format($plain);
    }

    protected static function format(string $plain): string
    {
        return implode('-', str_split($plain, 4));
    }
}
