<?php

namespace App\Filament\Concerns;

use App\Support\Modules;

/**
 * For resources: the user must have the resource's module (see
 * Modules::CLASS_MAP) as well as passing the resource's own checks.
 * Filament uses canAccess() both for the menu and for every page of the
 * resource, so this hides the item and blocks its URLs together.
 */
trait GatedByModule
{
    public static function canAccess(): bool
    {
        return Modules::allowsClass(static::class) && parent::canAccess();
    }
}
