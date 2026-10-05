<?php

use App\Exceptions\PlanLimitReached;
use App\Http\Middleware\SecurityHeaders;
use App\Support\ErrorRecorder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        // Called by the Windows app and by SchoolPay, not browser forms
        // (rate limited and signed instead).
        $middleware->validateCsrfTokens(except: ['licence/activate', 'webhooks/schoolpay/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Explained to the user as a notice, never an error.
        $exceptions->dontReport([PlanLimitReached::class]);

        // Every unexpected error goes to the platform owner's Error reports
        // (and the log file, as before), with a reference for the user.
        $exceptions->report(function (Throwable $e): void {
            app(ErrorRecorder::class)->record($e);
        });

        // The reference travels with the error response, so the error page
        // and the notice for a failed background action can show it.
        $exceptions->respond(function (Response $response): Response {
            $reference = app(ErrorRecorder::class)->reference();

            if ($reference && $response->getStatusCode() >= 500) {
                $response->headers->set('X-Error-Reference', $reference);
            }

            return $response;
        });
    })->create();
