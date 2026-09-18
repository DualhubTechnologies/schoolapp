<?php

namespace App\Filament\Admin\Resources\Schools\Pages;

use App\Filament\Admin\Resources\Schools\SchoolResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateSchool extends CreateRecord
{
    protected static string $resource = SchoolResource::class;

    /**
     * Resource pages cap their own width regardless of the panel's
     * maxContentWidth, which is what leaves dead space beside the form.
     */
    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }
}