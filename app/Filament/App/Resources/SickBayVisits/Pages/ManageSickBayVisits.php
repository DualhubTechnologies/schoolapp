<?php

namespace App\Filament\App\Resources\SickBayVisits\Pages;

use App\Filament\App\Resources\SickBayVisits\SickBayVisitResource;
use App\Models\SickBayVisit;
use App\Models\Term;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSickBayVisits extends ManageRecords
{
    protected static string $resource = SickBayVisitResource::class;

    public function getSubheading(): ?string
    {
        return 'Everyone seen at the sick bay. Parents are texted when a learner is sent home or referred; the badge in the menu shows who is resting in the sick bay now.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New visit')
                ->modalWidth('2xl')
                ->mutateDataUsing(function (array $data): array {
                    $schoolId = auth()->user()?->school_id;
                    SickBayVisitResource::$textParent = (bool) ($data['notify_parent'] ?? false);
                    unset($data['notify_parent']);

                    return $data + [
                        'school_id' => $schoolId,
                        'term_id' => Term::current($schoolId)?->getKey(),
                        'attended_by' => auth()->user()?->name,
                    ];
                })
                ->after(fn (SickBayVisit $record) => SickBayVisitResource::textParentIfAsked($record)),
        ];
    }
}
