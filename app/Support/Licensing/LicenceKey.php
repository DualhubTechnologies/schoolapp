<?php

namespace App\Support\Licensing;

use Carbon\CarbonImmutable;
use JsonException;
use SodiumException;

/**
 * A Windows app licence: who it is for, what it allows and for how long,
 * signed by SchoolHub so the app can check it without internet.
 *
 * The key is "SHL1." + the details (JSON, base64url) + "." + an Ed25519
 * signature of them (base64url). Only the server holds the private key
 * (LICENCE_PRIVATE_KEY) that signs; the app carries the public key
 * (config licence.public_key) that checks. Changing a single letter of a
 * key, or writing one from scratch, gives a signature that does not match.
 */
final class LicenceKey
{
    public const PREFIX = 'SHL1';

    /**
     * @param  array{id: string, school: string, code: string, plan: string, students: ?int, users: ?int, cycle: string, starts: string, ends: string, issued: string}  $details
     */
    public function __construct(public readonly array $details) {}

    /**
     * Sign the details with the server's private key (base64 of the
     * 64-byte Ed25519 secret key).
     *
     * @param  array{id: string, school: string, code: string, plan: string, students: ?int, users: ?int, cycle: string, starts: string, ends: string, issued: string}  $details
     */
    public static function sign(array $details, string $privateKey): string
    {
        $secret = base64_decode($privateKey, true);

        if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new \RuntimeException('LICENCE_PRIVATE_KEY is not a valid signing key. Run php artisan licence:keygen on the server.');
        }

        $body = self::base64url(json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $signature = sodium_crypto_sign_detached(self::PREFIX.'.'.$body, $secret);

        return self::PREFIX.'.'.$body.'.'.self::base64url($signature);
    }

    /**
     * The licence, if the key is well formed and SchoolHub signed it;
     * null for anything else (typos, edits, made-up keys).
     */
    public static function verify(string $key, string $publicKey): ?self
    {
        $parts = explode('.', trim(preg_replace('/\s+/', '', $key) ?? ''));
        $public = base64_decode($publicKey, true);

        if (count($parts) !== 3 || $parts[0] !== self::PREFIX || $public === false || strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return null;
        }

        $signature = self::unbase64url($parts[2]);
        $json = self::unbase64url($parts[1]);

        if ($signature === null || $json === null || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return null;
        }

        try {
            if (! sodium_crypto_sign_verify_detached($signature, self::PREFIX.'.'.$parts[1], $public)) {
                return null;
            }

            $details = json_decode($json, true, 4, JSON_THROW_ON_ERROR);
        } catch (SodiumException|JsonException) {
            return null;
        }

        foreach (['id', 'school', 'code', 'plan', 'cycle', 'starts', 'ends', 'issued'] as $field) {
            if (! is_array($details) || ! isset($details[$field]) || ! is_string($details[$field])) {
                return null;
            }
        }

        return new self([
            'id' => $details['id'],
            'school' => $details['school'],
            'code' => $details['code'],
            'plan' => $details['plan'],
            'students' => isset($details['students']) ? (int) $details['students'] : null,
            'users' => isset($details['users']) ? (int) $details['users'] : null,
            'cycle' => $details['cycle'],
            'starts' => $details['starts'],
            'ends' => $details['ends'],
            'issued' => $details['issued'],
        ]);
    }

    /** Whether this licence was made for this school (name and code, ignoring case and spacing). */
    public function isFor(string $schoolName, string $schoolCode): bool
    {
        $same = fn (string $a, string $b): bool => mb_strtolower(preg_replace('/\s+/', ' ', trim($a)) ?? '') === mb_strtolower(preg_replace('/\s+/', ' ', trim($b)) ?? '');

        return $same($this->details['school'], $schoolName) && $same($this->details['code'], $schoolCode);
    }

    public function startsOn(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->details['starts'])->startOfDay();
    }

    public function endsOn(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->details['ends'])->startOfDay();
    }

    protected static function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    protected static function unbase64url(string $text): ?string
    {
        $decoded = base64_decode(strtr($text, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
