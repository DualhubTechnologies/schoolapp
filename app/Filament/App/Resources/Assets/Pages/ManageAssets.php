<?php

namespace App\Filament\App\Resources\Assets\Pages;

use App\Filament\App\Resources\Assets\AssetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAssets extends ManageRecords
{
    protected static string $resource = AssetResource::class;

    protected static ?string $title = 'Asset Register';

    public function getSubheading(): ?string
    {
        return 'What the school owns, where it is and the state it is in. Mark each item with its tag number.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New asset')
                ->mutateDataUsing(fn (array $data): array => $data + ['school_id' => auth()->user()?->school_id])
                ->successNotificationTitle(fn ($record): string => 'Added as '.$record->tag_no.': mark this number on it'),
        ];
    }
}
