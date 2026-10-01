<?php

/*
 * Licences for the Windows app (App\Support\Licensing).
 *
 * public_key   checks licence keys. Not secret: it is built into the app.
 *              Set it from the output of `php artisan licence:keygen`.
 * private_key  signs licence keys. SECRET: only on the online server, in
 *              .env, never in the code or the Windows app.
 */
return [

    // SchoolHub's public key, made on the server on 1 October 2026. The
    // Windows app checks every licence with it.
    'public_key' => env('LICENCE_PUBLIC_KEY', 'GVdvAk4enebTPFszl3URZx+TUysX1OJ/eI1Wefwrr3I='),

    'private_key' => env('LICENCE_PRIVATE_KEY'),

    // A computer clock this far behind the latest time the app has seen is
    // treated as set back (to stretch a licence), and the app locks until
    // it is corrected.
    'clock_tolerance_hours' => 24,

];
