<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\ConfirmYourEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Confirming the email address of the person who registers a school: we
 * email a 6-digit code and they type it in before their first sign-in.
 *
 * Only accounts created by school registration get a code, so staff
 * logins made by a school, and every account from before this existed,
 * sign in as they always have. Only the code's hash is stored.
 */
class EmailVerificationCode
{
    public const LENGTH = 6;

    /** How long a code works for. */
    public const EXPIRES_AFTER_MINUTES = 30;

    /** Wrong guesses allowed before a new code is needed. */
    public const MAX_ATTEMPTS = 5;

    /** How long to wait before asking for another code. */
    public const RESEND_AFTER_SECONDS = 60;

    /** The account the verification page is for, kept in the session. */
    public const SESSION_KEY = 'schoolhub.email_verification_user';

    public const VERIFIED = 'verified';

    public const WRONG = 'wrong';

    public const EXPIRED = 'expired';

    public const TOO_MANY_ATTEMPTS = 'too_many_attempts';

    /**
     * Whether this account must confirm its email before signing in.
     */
    public static function isPending(User $user): bool
    {
        return $user->email_verified_at === null && $user->email_verification_code !== null;
    }

    /**
     * Email a fresh code (replacing any earlier one). False when the mail
     * could not be sent, so the page can say so and offer to resend.
     */
    public static function send(User $user): bool
    {
        $code = str_pad((string) random_int(0, 10 ** self::LENGTH - 1), self::LENGTH, '0', STR_PAD_LEFT);

        $user->forceFill([
            'email_verification_code' => Hash::make($code),
            'email_verification_sent_at' => now(),
        ])->save();

        RateLimiter::clear(self::attemptsKey($user));

        try {
            $user->notify(new ConfirmYourEmail($code, self::EXPIRES_AFTER_MINUTES));
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        return true;
    }

    /**
     * Check a code typed in. On success the email is confirmed and the
     * code is removed.
     *
     * @return self::VERIFIED|self::WRONG|self::EXPIRED|self::TOO_MANY_ATTEMPTS
     */
    public static function attempt(User $user, string $code): string
    {
        if (RateLimiter::tooManyAttempts(self::attemptsKey($user), self::MAX_ATTEMPTS)) {
            return self::TOO_MANY_ATTEMPTS;
        }

        if (self::hasExpired($user)) {
            return self::EXPIRED;
        }

        $code = preg_replace('/\D/', '', $code) ?? '';

        if (! Hash::check($code, (string) $user->email_verification_code)) {
            RateLimiter::hit(self::attemptsKey($user), self::EXPIRES_AFTER_MINUTES * 60);

            return self::WRONG;
        }

        self::markVerified($user);

        return self::VERIFIED;
    }

    public static function markVerified(User $user): void
    {
        $user->forceFill([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_sent_at' => null,
        ])->save();

        RateLimiter::clear(self::attemptsKey($user));
    }

    public static function hasExpired(User $user): bool
    {
        return $user->email_verification_sent_at === null
            || $user->email_verification_sent_at->copy()->addMinutes(self::EXPIRES_AFTER_MINUTES)->isPast();
    }

    /**
     * Seconds until another code may be sent (0 = now).
     */
    public static function secondsUntilResend(User $user): int
    {
        if ($user->email_verification_sent_at === null) {
            return 0;
        }

        $availableAt = $user->email_verification_sent_at->copy()->addSeconds(self::RESEND_AFTER_SECONDS);

        return max(0, (int) ceil(now()->diffInSeconds($availableAt, false)));
    }

    /**
     * Wrong guesses left on the current code.
     */
    public static function attemptsLeft(User $user): int
    {
        return RateLimiter::remaining(self::attemptsKey($user), self::MAX_ATTEMPTS);
    }

    /**
     * Remember, for this browser, whose email the verification page is
     * confirming (set after registration and after a correct password).
     */
    public static function rememberFor(User $user): void
    {
        session()->put(self::SESSION_KEY, $user->getKey());
    }

    public static function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public static function pendingUser(): ?User
    {
        $id = session()->get(self::SESSION_KEY);

        return $id ? User::whereKey($id)->first() : null;
    }

    /**
     * "gr•••••@gmail.com": enough to recognise, not enough to harvest.
     */
    public static function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $visible = mb_substr($name, 0, min(2, max(1, mb_strlen($name) - 1)));

        return $visible.str_repeat('•', 4).'@'.$domain;
    }

    protected static function attemptsKey(User $user): string
    {
        return 'email-verification-code:'.$user->getKey();
    }
}
