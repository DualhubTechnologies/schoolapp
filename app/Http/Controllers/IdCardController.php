<?php

namespace App\Http\Controllers;

use App\Models\IdCardTemplate;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Services\IdCardService;
use App\Support\Modules;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Student and staff ID cards from the school's template, front and back: a
 * plain print view sized for a card printer (one person at a time or a
 * whole batch), and a PDF export laid out on A4 sheets for schools that
 * take printing to an outside shop.
 *
 * Both refuse a batch where anyone is missing something the card needs --
 * the ID Cards pages already disable Print/Export in that case, this is
 * the server-side half of that same rule.
 */
class IdCardController extends Controller
{
    public function __construct(protected IdCardService $cards) {}

    public function print(Request $request, string $type): View
    {
        return view('id-cards.print', $this->viewData($this->resolveHolders($request, $type)));
    }

    public function export(Request $request, string $type): Response
    {
        $pdf = Pdf::loadView('id-cards.pdf', $this->viewData($this->resolveHolders($request, $type)));

        $pdf->setPaper('a4');

        return $pdf->download(($type === 'staff' ? 'staff' : 'student').'-id-cards-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * The fronts, the shared back and the colours, all from the school's
     * saved template. Everyone in a batch belongs to the signed-in school.
     *
     * @param  Collection<int, Student>|Collection<int, Staff>  $holders
     * @return array<string, mixed>
     */
    protected function viewData(Collection $holders): array
    {
        $school = auth()->user()?->school;

        abort_unless($school instanceof School, 403);

        $template = IdCardTemplate::forSchool($school->id);

        return [
            'cards' => $this->cards->cardsFor($holders, $template),
            'shared' => $this->cards->schoolData($school, $template),
            'design' => $this->cards->design($template),
        ];
    }

    /**
     * The requested students or staff, scoped to the signed-in school, in
     * name order -- and refused as a batch if any one of them is not ready,
     * so a school never ends up with a stack of cards some of which are
     * missing details.
     *
     * @return Collection<int, Student>|Collection<int, Staff>
     */
    protected function resolveHolders(Request $request, string $type): Collection
    {
        abort_unless(Modules::allows('id_cards'), 403);

        $schoolId = auth()->user()?->school_id;
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $holders = $type === 'staff'
            ? Staff::query()->whereKey($ids)->where('school_id', $schoolId)->with('school')->orderBy('name')->get()
            : Student::query()->whereKey($ids)->where('school_id', $schoolId)
                ->with(['school', 'guardian', 'schoolClass', 'section', 'house', 'residencyType'])
                ->orderBy('name')
                ->get();

        abort_if($holders->isEmpty(), 404, 'No one chosen to print.');

        $notReady = $holders->reject(fn (Student|Staff $holder) => $this->cards->isReady($holder));

        abort_if($notReady->isNotEmpty(), 422, 'Some of the chosen cards are missing details: '
            .$notReady->map(fn (Student|Staff $holder) => $holder->name)->implode(', ').'.');

        return $holders;
    }
}
