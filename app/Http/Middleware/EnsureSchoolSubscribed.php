<?php

namespace App\Http\Middleware;

use App\Filament\Pages\SchoolSubscription;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A school whose subscription has run out (after the grace days), that
 * the platform owner has suspended, or that is still awaiting approval
 * can only see its Subscription page until that changes. Its data is
 * untouched.
 */
class EnsureSchoolSubscribed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $route = $request->route()?->getName() ?? '';

        $allowed = str_ends_with($route, '.auth.logout')
            || $route === SchoolSubscription::getRouteName();

        if ($allowed || ! static::locks($user)) {
            return $next($request);
        }

        return redirect()->to(SchoolSubscription::getUrl());
    }

    /**
     * Whether this person is held on the Subscription page, so the
     * sidebar and quick links (which would only lead back there) are
     * hidden too.
     */
    public static function locks(?User $user): bool
    {
        if (! $user?->school_id || $user->hasRole('Super Admin')) {
            return false;
        }

        return SubscriptionManager::isLocked($user->school_id);
    }
}
