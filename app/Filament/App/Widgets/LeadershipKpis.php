<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Resources\FeeBalances\FeeBalanceResource;
use App\Filament\App\Resources\Payments\PaymentResource;
use App\Filament\App\Resources\Staff\StaffResource;
use App\Filament\App\Resources\Students\StudentResource;
use App\Services\Dashboard\SchoolFigures;
use App\Support\Modules;

/** School Admin: the four numbers a head teacher asks about first (those of modules the school uses). */
class LeadershipKpis extends KpiCards
{
    public function cards(): array
    {
        $id = (int) $this->schoolId();
        $term = $this->currentTerm();
        $students = SchoolFigures::students($id, $term);
        $withFees = Modules::allows('fees');
        $fees = $withFees ? SchoolFigures::term($id, $term) : ['collected' => 0, 'billed' => 0];
        [$owed, $debtors] = $withFees ? SchoolFigures::outstanding($id) : [0, 0];
        $staff = SchoolFigures::staff($id);
        $rate = static::percent($fees['collected'], $fees['billed']);

        return array_values(array_filter([
            static::card('blue', 'heroicon-o-academic-cap', 'Active students', number_format($students['active']),
                $students['new'] ? "{$students['new']} admitted this term" : 'No new admissions this term',
                StudentResource::getUrl(), trend: $students['new'] ? 'up' : null),
            $withFees ? static::card('emerald', 'heroicon-o-banknotes', 'Fees collected this term', static::shortMoney($fees['collected']),
                $fees['billed'] > 0 ? static::percentText($fees['collected'], $fees['billed']).' of '.static::shortMoney($fees['billed']).' billed' : 'Nothing billed yet this term',
                PaymentResource::getUrl(), $rate) : null,
            $withFees ? static::card('amber', 'heroicon-o-scale', 'Outstanding fees', static::shortMoney($owed),
                $debtors ? number_format($debtors).' '.str('student')->plural($debtors).' owe, all terms' : 'Everyone is paid up',
                FeeBalanceResource::getUrl()) : null,
            static::card('violet', 'heroicon-o-user-group', 'Active staff', number_format($staff['active']),
                "{$staff['teaching']} teaching · {$staff['non_teaching']} non-teaching",
                Modules::allows('hr') ? StaffResource::getUrl() : null),
        ]));
    }
}
