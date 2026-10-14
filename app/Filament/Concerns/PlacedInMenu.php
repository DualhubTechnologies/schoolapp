<?php

namespace App\Filament\Concerns;

use App\Support\Menu;
use UnitEnum;

/**
 * A page or resource takes its menu group, position and label from the
 * one menu map (App\Support\Menu), so the whole sidebar is arranged in a
 * single place.
 */
trait PlacedInMenu
{
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return Menu::place(static::class)[0] ?? parent::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return Menu::place(static::class)[1] ?? parent::getNavigationSort();
    }

    public static function getNavigationLabel(): string
    {
        return Menu::place(static::class)[2] ?? parent::getNavigationLabel();
    }
}
