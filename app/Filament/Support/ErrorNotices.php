<?php

namespace App\Filament\Support;

use Filament\Panel;

/**
 * What Filament says when a background action (saving a form, pressing a
 * button) fails, in plain words, per HTTP status. Server errors (5xx) that
 * carry a reference are shown with it by public/js/schoolhub-mobile.js;
 * connection loss, an expired session (419) and updates (503) are handled
 * there too.
 */
class ErrorNotices
{
    public static function register(Panel $panel): Panel
    {
        return $panel
            ->errorNotifications()
            ->registerErrorNotification(
                title: 'Something went wrong',
                body: 'Please refresh the page and try again. If it keeps happening, contact SchoolHub on WhatsApp.',
            )
            ->registerErrorNotification(
                title: 'You don\'t have access to do that',
                body: 'If you need it for your work, ask your school administrator for access under Settings → Users.',
                statusCode: 403,
            )
            ->registerErrorNotification(
                title: 'That record is no longer there',
                body: 'Someone may have deleted it. Refresh the page to see the latest.',
                statusCode: 404,
            )
            ->registerErrorNotification(
                title: 'That file is too large',
                body: 'Choose a smaller picture or file (up to 10 MB) and try again.',
                statusCode: 413,
            )
            ->registerErrorNotification(
                title: 'Too many attempts',
                body: 'Please wait a minute, then try again.',
                statusCode: 429,
            )
            ->registerErrorNotification(
                title: 'Something went wrong on our side',
                body: 'It was not your fault, and the SchoolHub team has been told. Please try again.',
                statusCode: 500,
            );
    }
}
