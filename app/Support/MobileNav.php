<?php

namespace App\Support;

use App\Filament\App\Pages\Dashboard;
use App\Filament\App\Resources\Expenses\ExpenseResource;
use App\Filament\App\Resources\FeeBalances\FeeBalanceResource;
use App\Filament\App\Resources\PayrollPeriods\PayrollPeriodResource;
use App\Filament\App\Resources\Students\StudentResource;
use App\Filament\Pages\EnterMarks;
use App\Filament\Pages\ReceivePayment;
use App\Http\Middleware\EnsureSchoolSubscribed;
use App\Models\Student;
use App\Services\ParentPortal;

/**
 * The bar along the bottom of the screen on phones: Home, the three or
 * four things this person does most, and Menu for everything else. What
 * is shown follows the modules they may open (Modules), so a bursar gets
 * fees and a teacher gets marks without anyone setting it up.
 */
class MobileNav
{
    /** Most used first; the first four the user may open are shown. */
    protected const CANDIDATES = [
        ['fees', 'Receive', 'heroicon-o-banknotes', ReceivePayment::class],
        ['exams', 'Marks', 'heroicon-o-pencil-square', EnterMarks::class],
        ['students', 'Learners', 'heroicon-o-academic-cap', StudentResource::class],
        ['fees', 'Balances', 'heroicon-o-scale', FeeBalanceResource::class],
        ['hr', 'Payroll', 'heroicon-o-wallet', PayrollPeriodResource::class],
        ['finance', 'Expenses', 'heroicon-o-arrow-up-tray', ExpenseResource::class],
    ];

    /**
     * @return list<array{label: string, icon: string, url: string, active: bool}>
     */
    public static function items(): array
    {
        $user = auth()->user();

        if (! $user?->school_id || EnsureSchoolSubscribed::locks($user)) {
            return [];
        }

        $items = [self::item('Home', 'heroicon-o-home', Dashboard::getUrl())];

        if (! self::showsMenu()) {
            return [...$items, ...self::childItems()];
        }

        foreach (self::CANDIDATES as [$module, $label, $icon, $class]) {
            if (count($items) === 4) {
                break;
            }

            if (Modules::allows($module) && $class::canAccess()) {
                $items[] = self::item($label, $icon, $class::getUrl());
            }
        }

        return $items;
    }

    /**
     * Parents have no sidebar (AppPanelProvider), so no Menu button.
     */
    public static function showsMenu(): bool
    {
        return DashboardProfile::for() !== DashboardProfile::PARENT;
    }

    /**
     * A parent's bar: each child's fees and report cards page, by first
     * name ("Fees & reports" when there is only one child).
     *
     * @return list<array{label: string, icon: string, url: string, active: bool}>
     */
    protected static function childItems(): array
    {
        $user = auth()->user();
        $children = $user ? app(ParentPortal::class)->children($user)->take(3) : collect();

        return $children
            ->map(fn (Student $child): array => self::item(
                $children->count() === 1 ? 'Fees & reports' : (string) strtok((string) $child->name, ' '),
                'heroicon-o-document-text',
                $child->parentPageUrl(),
            ))
            ->values()
            ->all();
    }

    /**
     * @return array{label: string, icon: string, url: string, active: bool}
     */
    protected static function item(string $label, string $icon, string $url): array
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $current = trim(request()->path(), '/');

        return [
            'label' => $label,
            'icon' => $icon,
            'url' => $url,
            'active' => $path === $current || ($path !== '' && $path !== 'dashboard' && str_starts_with($current, $path.'/')),
        ];
    }
}
