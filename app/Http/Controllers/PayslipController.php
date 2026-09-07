<?php

namespace App\Http\Controllers;

use App\Models\PayrollEntry;
use Barryvdh\DomPDF\Facade\Pdf;

class PayslipController extends Controller
{
    public function download(PayrollEntry $entry)
    {
        $entry->load(['staff', 'payrollPeriod.school', 'items']);

        $school = $entry->payrollPeriod->school;
        $period = $entry->payrollPeriod;
        $bankDetail = $entry->staff->bankDetails()->where('is_primary', true)->first();

        $allowanceItems = $entry->items->where('category', 'allowance');
        $statutoryItems = $entry->items->where('category', 'statutory');
        $deductionItems = $entry->items->where('category', 'deduction');
        $arrearItems = $entry->items->where('category', 'arrears');

        $pdf = Pdf::loadView('payslips.payslip', compact(
            'entry', 'school', 'period', 'bankDetail',
            'allowanceItems', 'statutoryItems', 'deductionItems', 'arrearItems'
        ));

        $filename = "Payslip-{$entry->staff->staff_no}-{$period->period_label}.pdf";

        return $pdf->download($filename);
    }
}