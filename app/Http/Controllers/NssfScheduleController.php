<?php

namespace App\Http\Controllers;

use App\Models\PayrollPeriod;
use App\Exports\NssfScheduleExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class NssfScheduleController extends Controller
{
    public function downloadPdf(PayrollPeriod $period)
    {
        $period->load(['school', 'entries.staff']);

        $school = $period->school;
        $entries = $period->entries()
            ->with('staff')
            ->where('status', 'included')
            ->get();

        $pdf = Pdf::loadView('payslips.nssf-schedule', compact('period', 'school', 'entries'))
            ->setPaper('a4', 'landscape');

        $filename = "NSSF-Schedule-{$period->period_label}.pdf";

        return $pdf->download($filename);
    }

    public function downloadExcel(PayrollPeriod $period)
    {
        $filename = "NSSF-Schedule-{$period->period_label}.xlsx";

        return Excel::download(new NssfScheduleExport($period), $filename);
    }
}