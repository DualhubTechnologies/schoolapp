<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Resources\FeeBalances\FeeBalanceResource;
use App\Filament\App\Resources\Payments\PaymentResource;
use App\Services\Dashboard\SchoolFigures;

/** Bursar / accounts: what came in today, this week and this term, and what is still owed. */
class BursarKpis extends KpiCards
{
    public function cards(): array
    {
        $id = (int) $this->schoolId();
        $term = $this->currentTerm();
        $today = SchoolFigures::collected($id, today(), today());
        $week = SchoolFigures::collected($id, today()->startOfWeek(), today());
        $fees = SchoolFigures::term($id, $term);
        [$owed, $debtors] = SchoolFigures::outstanding($id);
        $rate = static::percent($fees['collected'], $fees['billed']);
        $payments = PaymentResource::getUrl();

        return [
            static::card('emerald', 'heroicon-o-banknotes', 'Collected today', static::shortMoney($today['amount']),
                $today['count'] ? $today['count'] . ' ' . str('receipt')->plural($today['count']) . ' issued' : 'No payments yet today', $payments),
            static::card('blue', 'heroicon-o-calendar-days', 'This week', static::shortMoney($week['amount']),
                $week['count'] . ' ' . str('payment')->plural($week['count']) . ' since Monday', $payments),
            static::card('violet', 'heroicon-o-chart-pie', 'This term', static::shortMoney($fees['collected']),
                $fees['billed'] > 0 ? static::percentText($fees['collected'], $fees['billed']) . ' of ' . static::shortMoney($fees['billed']) . ' billed' : 'Nothing billed yet this term',
                $payments, $rate),
            static::card('rose', 'heroicon-o-exclamation-triangle', 'Outstanding', static::shortMoney($owed),
                $debtors ? number_format($debtors) . ' ' . str('student')->plural($debtors) . ' with balances' : 'Everyone is paid up',
                FeeBalanceResource::getUrl()),
        ];
    }
}
