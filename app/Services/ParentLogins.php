<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Logins for parents. A parent signs in with their phone number and a
 * 6-digit PIN (App\Filament\Pages\Auth\Login), and sees only their own
 * children (App\Filament\App\Widgets\ParentChildren).
 *
 * A parent recorded twice (one record per child) gets one login: a
 * second record with the same phone is linked to the first login.
 */
class ParentLogins
{
    public function __construct(protected SmsSender $sms) {}

    /** Whether the school's plan includes parent logins (the platform owner is never limited). */
    public static function planAllows(?int $schoolId = null): bool
    {
        if (auth()->user()?->hasRole('Super Admin')) {
            return true;
        }

        $plan = SubscriptionManager::current($schoolId ?? auth()->user()?->school_id)?->plan;

        return $plan?->parent_student_login ?? true;
    }

    /**
     * Give the parent a login, or link them to the one their phone already
     * has at this school. The PIN is only returned for a new login.
     *
     * @return array{user: User, pin: ?string, texted: bool, error: ?string}
     *
     * @throws InvalidArgumentException when the parent has no usable phone number
     */
    public function createFor(Guardian $guardian, bool $text = true): array
    {
        $phone = SmsSender::normalisePhone($guardian->phone);

        if ($phone === null) {
            throw new InvalidArgumentException("{$guardian->name} has no valid mobile number. Add one (e.g. 0772 123456) first.");
        }

        if ($guardian->user) {
            return ['user' => $guardian->user, 'pin' => null, 'texted' => false, 'error' => null];
        }

        $existing = User::where('school_id', $guardian->school_id)
            ->where('phone', $phone)
            ->whereHas('roles', fn ($q) => $q->where('name', 'Parent'))
            ->first();

        if ($existing) {
            $guardian->update(['user_id' => $existing->getKey()]);

            return ['user' => $existing, 'pin' => null, 'texted' => false, 'error' => null];
        }

        $pin = (string) random_int(100000, 999999);

        $user = DB::transaction(function () use ($guardian, $phone, $pin): User {
            $user = new User;
            $user->forceFill([
                'name' => $guardian->name,
                'email' => $this->emailFor($guardian),
                'phone' => $phone,
                'password' => Hash::make($pin),
                'school_id' => $guardian->school_id,
                'email_verified_at' => now(),
            ])->save();

            $user->assignRole('Parent');
            $guardian->update(['user_id' => $user->getKey()]);

            return $user;
        });

        $error = null;

        if ($text) {
            $result = $this->sms->send($phone, $this->message($guardian, $pin));
            $error = $result['ok'] ? null : ($result['error'] ?? 'The SMS could not be sent.');
        }

        return ['user' => $user, 'pin' => $pin, 'texted' => $text && $error === null, 'error' => $error];
    }

    /**
     * A new PIN for a parent who forgot theirs.
     *
     * @return array{pin: string, texted: bool, error: ?string}
     */
    public function resetPin(Guardian $guardian, bool $text = true): array
    {
        $user = $guardian->user ?? throw new InvalidArgumentException("{$guardian->name} has no login yet.");
        $pin = (string) random_int(100000, 999999);
        $user->forceFill(['password' => Hash::make($pin)])->save();

        $error = null;

        if ($text && $user->phone) {
            $result = $this->sms->send($user->phone, $this->message($guardian, $pin));
            $error = $result['ok'] ? null : ($result['error'] ?? 'The SMS could not be sent.');
        }

        return ['pin' => $pin, 'texted' => $text && $user->phone && $error === null, 'error' => $error];
    }

    /** The sign-in details, short enough for one SMS. */
    public function message(Guardian $guardian, string $pin): string
    {
        return "{$guardian->school?->name}: your SchoolHub parent login. Go to ".str_replace(['https://', 'http://'], '', url('/login'))
            ." and sign in with phone {$guardian->phone} and PIN {$pin} to see fees, receipts and report cards.";
    }

    /**
     * The parent's own email when they gave one and no one uses it yet;
     * otherwise a private address that only identifies the login (parents
     * sign in with their phone).
     */
    protected function emailFor(Guardian $guardian): string
    {
        $email = Str::lower(trim((string) $guardian->email));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) && ! User::where('email', $email)->exists()) {
            return $email;
        }

        return "parent.{$guardian->getKey()}.".Str::lower(Str::random(6)).'@parents.schoolhubug.com';
    }
}
