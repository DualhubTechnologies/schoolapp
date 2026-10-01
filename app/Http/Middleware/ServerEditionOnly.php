<?php

namespace App\Http\Middleware;

use App\Support\Edition;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Parts of SchoolHub that only make sense online: the public website,
 * the sitemap, parents' links, the demo form and the platform owner's
 * panel. In the Windows app they are not there at all.
 */
class ServerEditionOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(Edition::isDesktop(), 404);

        return $next($request);
    }
}
