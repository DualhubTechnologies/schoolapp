<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\OnboardingFunnel;
use Filament\Widgets\Widget;

/**
 * Super Admin dashboard: where new schools stop on their way from
 * registering to taking their first fees payment, and who to call.
 */
class SchoolOnboardingFunnel extends Widget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.school-onboarding-funnel';

    public static function canView(): bool
    {
        return filament()->getCurrentPanel()?->getId() === 'admin'
            && (auth()->user()?->hasRole('Super Admin') ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return app(OnboardingFunnel::class)->build();
    }
}
