<?php

namespace App\Http\Middleware;

use App\Filament\Pages\SchoolSubscription;
use App\Services\Subscriptions\SubscriptionManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A school whose subscription has run out (after the grace days), or that
 * the platform owner has suspended, can only see its Subscription page
 * until payment is recorded. Its data is untouched.
 */
class EnsureSchoolSubscribed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->school_id || $user->hasRole('Super Admin')) {
            return $next($request);
        }

        $route = $request->route()?->getName() ?? '';

        $allowed = str_ends_with($route, '.auth.logout')
            || $route === SchoolSubscription::getRouteName();

        if ($allowed || ! SubscriptionManager::isLocked($user->school_id)) {
            return $next($request);
        }

        return redirect()->to(SchoolSubscription::getUrl());
    }
}
