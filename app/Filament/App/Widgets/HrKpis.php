<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Resources\PayrollPeriods\PayrollPeriodResource;
use App\Filament\App\Resources\SalaryArrears\SalaryArrearResource;
use App\Filament\App\Resources\Staff\StaffResource;
use App\Models\PayrollPeriod;
use App\Models\SalaryArrear;
use App\Services\Dashboard\SchoolFigures;

/** HR: headcount, the latest payroll and anything waiting on salaries. */
class HrKpis extends KpiCards
{
    public function cards(): array
    {
        $id = (int) $this->schoolId();
        $staff = SchoolFigures::staff($id);
        $latest = PayrollPeriod::where('school_id', $id)->orderByDesc('year')->orderByDesc('month')->first();
        $arrears = SalaryArrear::where('school_id', $id)->where('status', 'pending')->count();

        return [
            static::card('blue', 'heroicon-o-user-group', 'Active staff', number_format($staff['active']),
                'On the payroll', StaffResource::getUrl()),
            static::card('teal', 'heroicon-o-academic-cap', 'Teaching / non-teaching', "{$staff['teaching']} / {$staff['non_teaching']}",
                'Active staff by category', StaffResource::getUrl()),
            static::card('violet', 'heroicon-o-calculator', 'Latest payroll',
                $latest ? static::shortMoney((float) $latest->total_net) : '—',
                $latest ? "{$latest->period_label} · ".(PayrollPeriod::STATUSES[$latest->status] ?? $latest->status) : 'No payroll run yet',
                PayrollPeriodResource::getUrl()),
            static::card($arrears ? 'rose' : 'emerald', 'heroicon-o-exclamation-circle', 'Salary arrears', number_format($arrears),
                $arrears ? 'Pending review' : 'Nothing pending', SalaryArrearResource::getUrl()),
        ];
    }
}
