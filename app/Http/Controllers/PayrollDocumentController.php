<?php

namespace App\Http\Controllers;

use App\Models\PayrollPeriod;
use App\Support\PayrollAccess;
use Illuminate\View\View;

/**
 * Printable monthly payroll schedules:
 *
 *   paye   PAYE per employee with TIN -- for the URA monthly return
 *   lst    Local Service Tax deducted -- for the local government
 *   bank   net pay per employee with bank / mobile money details -- what
 *          the bursar hands the bank, or pays out from
 */
class PayrollDocumentController extends Controller
{
    public const TYPES = [
        'paye' => 'PAYE schedule',
        'lst' => 'Local Service Tax schedule',
        'bank' => 'Salary payment list',
    ];

    public function schedule(PayrollPeriod $period, string $type): View
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);
        PayrollAccess::authorize($period->school_id);

        $entries = $period->entries()
            ->where('status', 'included')
            ->with(['staff.primaryBankDetail'])
            ->get()
            ->sortBy(fn ($e) => $e->staff?->name)
            ->when($type === 'lst', fn ($c) => $c->filter(fn ($e) => (float) $e->lst > 0))
            ->values();

        return view('payroll.schedule', [
            'period' => $period,
            'school' => $period->school,
            'type' => $type,
            'title' => self::TYPES[$type],
            'entries' => $entries,
        ]);
    }
}
