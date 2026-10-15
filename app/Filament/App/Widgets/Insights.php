<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Pages\AiInsights;
use App\Services\SchoolInsights;
use Filament\Widgets\Widget;

/**
 * The AI Insights pop-up on the dashboard: what the school's own records
 * point out (SchoolInsights). It shows once a day at most, the first time
 * the dashboard opens with something to say, and closes with a click; the
 * full list is always under "AI Insights" in the menu.
 */
class Insights extends Widget
{
    /** How many findings the pop-up lists before "See all". */
    public const SHOWN = 4;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.app.widgets.insights';

    /** @var list<array{key: string, tone: string, icon: string, title: string, detail: ?string, url: ?string}> */
    public array $findings = [];

    public static function canView(): bool
    {
        return AiInsights::canAccess();
    }

    public function mount(): void
    {
        $user = auth()->user();
        $today = now('Africa/Kampala')->toDateString();

        if (! $user || $user->insights_seen_on?->toDateString() === $today) {
            return;
        }

        $this->findings = SchoolInsights::for($user);

        // Seen once it has shown: not again today, however often the
        // dashboard is opened or the user signs in.
        if ($this->findings) {
            $user->forceFill(['insights_seen_on' => $today])->save();
        }
    }
}
