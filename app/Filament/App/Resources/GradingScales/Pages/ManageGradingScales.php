<?php

namespace App\Filament\App\Resources\GradingScales\Pages;

use App\Filament\App\Resources\GradingScales\GradingScaleResource;
use Filament\Resources\Pages\ManageRecords;

class ManageGradingScales extends ManageRecords
{
    protected static string $resource = GradingScaleResource::class;

    public function getSubheading(): ?string
    {
        return 'How percentages become grades. Changing a scale regrades every result immediately, past terms included.';
    }
}
