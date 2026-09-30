<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser protections on every response:
 *
 *   - pages cannot be framed by other sites (clickjacking)
 *   - files are not sniffed into another type
 *   - links to other sites do not carry our full URLs (parent links hold
 *     a private token)
 *   - the camera is ours only (photo capture), no microphone or location
 *   - over HTTPS in production, browsers stay on HTTPS for a year
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Frame-Options', 'SAMEORIGIN', false);
        $headers->set('X-Content-Type-Options', 'nosniff', false);
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=()', false);

        if ($request->isSecure() && app()->isProduction()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000', false);
        }

        return $response;
    }
}
