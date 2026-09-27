<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A row in Laravel's `sessions` table (the database session driver): one
 * per signed-in browser, touched on every request. Read-only here — it is
 * how the platform owner sees who is using SchoolHub right now.
 *
 * @property string $id
 * @property int|null $user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property int $last_activity
 */
class UserSession extends Model
{
    /** Seen within this many minutes counts as "active now". */
    public const ACTIVE_MINUTES = 5;

    protected $table = 'sessions';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Sessions of signed-in users that have not yet expired.
     *
     * @param  Builder<UserSession>  $query
     */
    public function scopeSignedIn(Builder $query): void
    {
        $query->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime'))->getTimestamp());
    }

    /**
     * @param  Builder<UserSession>  $query
     */
    public function scopeActiveNow(Builder $query): void
    {
        $query->whereNotNull('user_id')->where('last_activity', '>=', self::activeSince());
    }

    /**
     * The timestamp after which a session counts as active now.
     */
    public static function activeSince(): int
    {
        return now()->subMinutes(self::ACTIVE_MINUTES)->getTimestamp();
    }

    public function lastSeen(): Carbon
    {
        return Carbon::createFromTimestamp($this->last_activity, config('app.timezone'));
    }

    public function isActiveNow(): bool
    {
        return $this->last_activity >= self::activeSince();
    }

    /**
     * "Chrome on Android" from the user agent — enough to tell a phone
     * from an office computer without a parsing library.
     */
    public function device(): string
    {
        $agent = (string) $this->user_agent;

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'Chrome/') || str_contains($agent, 'CriOS/') => 'Chrome',
            str_contains($agent, 'Firefox/') || str_contains($agent, 'FxiOS/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Browser',
        };

        $system = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iPhone/iPad',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'Mac',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        return $system ? "{$browser} on {$system}" : $browser;
    }
}
