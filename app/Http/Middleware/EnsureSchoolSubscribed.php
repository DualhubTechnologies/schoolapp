<?php

namespace App\Http\Middleware;

use App\Filament\Pages\AwaitingApproval;
use App\Filament\Pages\SchoolSubscription;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A school whose subscription has run out (after the grace days), or that
 * the platform owner has suspended, can only see its Subscription page
 * until that changes; one awaiting approval (or turned down) only its
 * Awaiting approval page. Its data is untouched.
 */
class EnsureSchoolSubscribed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! static::locks($user)) {
            return $next($request);
        }

        // A school awaiting approval has nothing to pay yet: it waits on
        // its own page. Every other locked school goes to Subscription.
        $page = AwaitingApproval::isWaiting($user) ? AwaitingApproval::class : SchoolSubscription::class;

        $route = $request->route()?->getName() ?? '';

        if (str_ends_with($route, '.auth.logout') || $route === $page::getRouteName()) {
            return $next($request);
        }

        return redirect()->to($page::getUrl());
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
