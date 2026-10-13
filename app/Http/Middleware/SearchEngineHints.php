<?php

namespace App\Http\Middleware;

use App\Support\Edition;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps Google's listing to the public website, under one address:
 *
 *   - the site answers on both schoolhubug.com and www.schoolhubug.com;
 *     the one APP_URL does not name is sent to the other (301), so Google
 *     lists each page once
 *   - /index.php/... shows the same page as /...; sent there too
 *   - everything that is not a public page (sign-in, registration, the
 *     panels, documents) carries X-Robots-Tag: noindex, so it never shows
 *     up in search results. It is a header, not a robots.txt Disallow,
 *     because Google must be able to fetch a page to see it is noindex.
 */
class SearchEngineHints
{
    /** Routes Google may list: the public website (see the sitemap). */
    private const PUBLIC_ROUTES = [
        'filament.app.landing',
        'filament.app.site.page',
        'filament.app.legal.terms',
        'robots',
        'sitemap',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (Edition::isServer() && $request->isMethodSafe() && ($redirect = $this->canonicalUrl($request))) {
            return redirect()->to($redirect, 301);
        }

        $response = $next($request);

        if (! in_array($request->route()?->getName(), self::PUBLIC_ROUTES, true)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow', false);
        }

        return $response;
    }

    /** Where this request belongs, if not where it is. */
    private function canonicalUrl(Request $request): ?string
    {
        $canonicalHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $host = $request->getHost();

        // Only the www / bare twin of APP_URL's host moves: a request by IP
        // address or on localhost is left alone.
        $wrongHost = $canonicalHost && $host !== $canonicalHost
            && preg_replace('/^www\./', '', $host) === preg_replace('/^www\./', '', $canonicalHost);

        $uri = $request->getRequestUri();
        $viaIndexPhp = preg_match('#^/index\.php(?=/|\?|$)#', $uri) === 1;

        if (! $wrongHost && ! $viaIndexPhp) {
            return null;
        }

        $path = $viaIndexPhp ? (preg_replace('#^/index\.php#', '', $uri) ?: '/') : $uri;
        $path = str_starts_with($path, '/') ? $path : '/'.$path;

        return $request->getScheme().'://'.($wrongHost ? $canonicalHost : $host).$path;
    }
}
