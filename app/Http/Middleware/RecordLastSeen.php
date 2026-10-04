<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Notes when the signed-in user was last seen, for the Users list. Written
 * at most once every few minutes, and straight to the column, so it never
 * bumps updated_at or fires model events.
 */
class RecordLastSeen
{
    /** Minutes between writes: "Last seen" is shown to about this accuracy. */
    public const EVERY_MINUTES = 5;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ($user->last_seen_at === null || $user->last_seen_at->lte(now()->subMinutes(self::EVERY_MINUTES)))) {
            $now = now();

            $user->newQuery()->whereKey($user->getKey())->toBase()->update(['last_seen_at' => $now]);
            $user->forceFill(['last_seen_at' => $now])->syncOriginalAttribute('last_seen_at');
        }

        return $next($request);
    }
}
