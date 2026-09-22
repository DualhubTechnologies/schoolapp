<?php

namespace App\Http\Controllers;

use App\Models\FinanceEntry;
use App\Support\FinanceAccess;
use Illuminate\View\View;

/**
 * Printable payment voucher (expense) or receipt voucher (other income).
 */
class FinanceDocumentController extends Controller
{
    public function voucher(int $entry): View
    {
        $entry = FinanceEntry::withVoided()->with(['category', 'school', 'term.academicYear'])->findOrFail($entry);

        abort_unless(FinanceAccess::allowed() && (int) auth()->user()->school_id === (int) $entry->school_id, 403);

        return view('finance.voucher', ['entry' => $entry, 'school' => $entry->school]);
    }
}
