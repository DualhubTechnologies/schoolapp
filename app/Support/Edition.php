<?php

namespace App\Support;

/**
 * Which SchoolHub this is (APP_EDITION in .env):
 *
 *   server   the online system at schoolhubug.com: many schools, the public
 *            website, registration and approval, subscriptions, and the
 *            platform owner's panel. The default.
 *   desktop  the Windows app: one school on one computer, working without
 *            internet. No public website, registration or owner panel; the
 *            school is created on first run (DesktopSetup).
 */
class Edition
{
    public static function isDesktop(): bool
    {
        return config('app.edition') === 'desktop';
    }

    public static function isServer(): bool
    {
        return ! static::isDesktop();
    }
}
