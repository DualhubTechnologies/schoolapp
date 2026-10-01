<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Support\Edition;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * In the Windows app, until the school has been set up there is nobody to
 * sign in: every page leads to the first-run setup (DesktopSetupController).
 * The online edition never stops here.
 */
class RequireDesktopSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Edition::isDesktop() && ! static::isSetUp()) {
            return redirect()->route('desktop.setup');
        }

        return $next($request);
    }

    /** One quick query; not remembered, so it is right the moment the school exists. */
    public static function isSetUp(): bool
    {
        return School::query()->exists();
    }
}
