<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Term;
use App\Models\TransportRoute;
use App\Services\Transport\TransportLedger;
use App\Support\Modules;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Printable route lists for the driver and the gate: one page per van
 * route with its learners, their parents' phones and whether this term's
 * van fee is paid.
 */
class TransportDocumentController extends Controller
{
    public function routeLists(Request $request, TransportLedger $ledger): View
    {
        $schoolId = auth()->user()?->school_id;

        abort_unless($schoolId && Modules::allows('transport'), 403);

        $routes = TransportRoute::query()
            ->where('school_id', $schoolId)
            ->when($request->integer('route'), fn ($query, int $id) => $query->whereKey($id))
            ->where('is_active', true)
            ->orderBy('name')
            ->with(['students' => fn ($query) => $query
                ->where('status', 'active')
                ->with(['schoolClass', 'section', 'guardian'])
                ->orderBy('name')])
            ->get();

        abort_if($routes->isEmpty(), 404);

        $term = Term::current($schoolId);

        // This term's van fee for each learner: paid, owing, or not billed yet.
        $status = $routes->flatMap->students->mapWithKeys(function (Student $student) use ($ledger, $term): array {
            $billed = $term ? ($ledger->forStudent($student)['terms'][$term->getKey()] ?? null) : null;

            return [$student->getKey() => $billed === null
                ? ['label' => 'Not billed', 'owed' => null]
                : ['label' => $billed['paid'] >= $billed['charged'] ? 'Paid' : 'Owes', 'owed' => max(0.0, $billed['charged'] - $billed['paid'])]];
        });

        return view('transport.route-lists', [
            'school' => auth()->user()->school,
            'routes' => $routes,
            'term' => $term,
            'status' => $status,
        ]);
    }
}
