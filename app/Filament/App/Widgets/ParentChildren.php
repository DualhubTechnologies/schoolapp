<?php

namespace App\Filament\App\Widgets;

use App\Services\ParentPortal;
use App\Support\DashboardProfile;
use Filament\Widgets\Widget;

/**
 * A parent's home screen: one card per child, with the fees balance,
 * this term's billing and payments, receipts, how to pay, report cards
 * the school has released and attendance.
 */
class ParentChildren extends Widget
{
    protected static ?int $sort = -5;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.app.widgets.parent-children';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return DashboardProfile::is(DashboardProfile::PARENT);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function children(): array
    {
        $user = auth()->user();
        $portal = app(ParentPortal::class);

        return $user ? $portal->children($user)->map(fn ($student) => $portal->overview($student))->values()->all() : [];
    }
}
