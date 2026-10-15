<?php

namespace App\Filament\App\Widgets;

use App\Services\SchoolInsights;
use App\Support\DashboardProfile;
use Filament\Widgets\Widget;

/**
 * "Worth knowing today" on the dashboard: what the school's own records
 * point out (SchoolInsights), each with a link to deal with it. Shows
 * nothing when there is nothing to say.
 */
class Insights extends Widget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.app.widgets.insights';

    public static function canView(): bool
    {
        return filled(auth()->user()?->school_id) && ! DashboardProfile::is(DashboardProfile::PLATFORM);
    }

    /** @return list<array{key: string, tone: string, icon: string, title: string, detail: ?string, url: ?string}> */
    public function findings(): array
    {
        $user = auth()->user();

        return $user ? SchoolInsights::for($user) : [];
    }
}
