<?php

namespace App\Filament\App\Pages;

use App\Services\SchoolInsights;
use App\Support\DashboardProfile;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * "Insights" in the menu, under Dashboard: everything the school's own
 * records point out today (SchoolInsights), open at any time. The same
 * findings pop up on the dashboard once a day (Widgets\Insights).
 */
class AiInsights extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'Insights';

    protected static ?int $navigationSort = -1;

    protected static ?string $title = 'Insights';

    protected static ?string $slug = 'insights';

    protected string $view = 'filament.app.pages.ai-insights';

    /** The head's and the bursar's: the findings are about the whole school's fees, attendance and marks. */
    public static function canAccess(): bool
    {
        return filled(auth()->user()?->school_id)
            && in_array(DashboardProfile::for(), [DashboardProfile::LEADERSHIP, DashboardProfile::BURSAR], true);
    }

    public function getSubheading(): ?string
    {
        return 'What your school\'s records point out today, worked out afresh every few minutes.';
    }

    /** @return list<array{key: string, tone: string, icon: string, title: string, detail: ?string, url: ?string}> */
    public function findings(): array
    {
        $user = auth()->user();

        return $user ? SchoolInsights::for($user) : [];
    }
}
