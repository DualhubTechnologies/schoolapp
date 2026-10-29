<?php

namespace App\Http\Middleware;

use App\Support\Edition;
use App\Support\PageTimings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Windows app: notes how long each page and action took (PageTimings), to
 * find what is slow on a school's own computer.
 */
class RecordPageTimings
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Edition::isDesktop()) {
            return $next($request);
        }

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $response = $next($request);

        $started = defined('LARAVEL_START') ? LARAVEL_START : $request->server('REQUEST_TIME_FLOAT', microtime(true));
        PageTimings::record($this->describe($request), (int) round((microtime(true) - (float) $started) * 1000), $queries);

        return $response;
    }

    /** "/students", or "Action on /students" for a button pressed there. */
    protected function describe(Request $request): string
    {
        $path = '/'.ltrim($request->path(), '/');

        if (str_contains($path, '/livewire') && str_ends_with($path, '/update')) {
            return 'Action on '.(parse_url((string) $request->headers->get('referer'), PHP_URL_PATH) ?: '?');
        }

        return preg_replace('#/\d+(?=/|$)#', '/{id}', $path) ?? $path;
    }
}
