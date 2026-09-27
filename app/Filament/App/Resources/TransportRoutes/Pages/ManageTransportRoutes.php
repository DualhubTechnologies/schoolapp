<?php

namespace App\Filament\App\Resources\TransportRoutes\Pages;

use App\Filament\App\Resources\TransportRoutes\TransportRouteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTransportRoutes extends ManageRecords
{
    protected static string $resource = TransportRouteResource::class;

    public function getSubheading(): ?string
    {
        return 'Your van routes and what each costs per term. Put learners on a route under Transport → Learners on the van; learners whose parents bring them pay nothing for transport.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New route')
                ->mutateDataUsing(fn (array $data): array => $data + ['school_id' => auth()->user()?->school_id]),
        ];
    }
}
