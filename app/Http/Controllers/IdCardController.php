<?php

namespace App\Http\Controllers;

use App\Models\IdCardTemplate;
use App\Models\School;
use App\Models\Student;
use App\Services\IdCardService;
use App\Support\Modules;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Student ID cards from the school's template, front and back: a plain
 * print view sized for a card printer (one student at a time or a whole
 * class), and a PDF export laid out on A4 sheets for schools that take
 * printing to an outside shop.
 *
 * Both refuse a student who is missing something the card needs -- the
 * Id Cards page already disables its Print/Export buttons in that case,
 * this is the server-side half of that same rule.
 */
class IdCardController extends Controller
{
    public function __construct(protected IdCardService $cards) {}

    public function print(Request $request): View
    {
        return view('id-cards.print', $this->viewData($this->resolveStudents($request)));
    }

    public function export(Request $request): Response
    {
        $pdf = Pdf::loadView('id-cards.pdf', $this->viewData($this->resolveStudents($request)));

        $pdf->setPaper('a4');

        return $pdf->download('id-cards-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * The fronts, the shared back and the colours, all from the school's
     * saved template. Every student in a batch belongs to the signed-in
     * school.
     *
     * @param  Collection<int, Student>  $students
     * @return array<string, mixed>
     */
    protected function viewData(Collection $students): array
    {
        $school = auth()->user()?->school;

        abort_unless($school instanceof School, 403);

        $template = IdCardTemplate::forSchool($school->id);

        return [
            'cards' => $this->cards->cardsFor($students, $template),
            'shared' => $this->cards->schoolData($school, $template),
            'design' => $this->cards->design($template),
        ];
    }

    /**
     * The requested students, scoped to the signed-in school, in the order
     * given -- and refused as a batch if any one of them is not ready, so
     * a school never ends up with a stack of cards half of which are blank
     * on the back.
     *
     * @return Collection<int, Student>
     */
    protected function resolveStudents(Request $request): Collection
    {
        abort_unless(Modules::allows('students'), 403);

        $schoolId = auth()->user()->school_id;
        $ids = collect(explode(',', (string) $request->query('students')))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique();

        $students = Student::query()
            ->whereKey($ids)
            ->where('school_id', $schoolId)
            ->with(['school', 'guardian', 'schoolClass', 'section', 'house', 'residencyType'])
            ->orderBy('name')
            ->get();

        abort_if($students->isEmpty(), 404, 'No students chosen to print.');

        $notReady = $students->reject(fn (Student $student) => $this->cards->isReady($student));

        abort_if($notReady->isNotEmpty(), 422, 'Some of the chosen students are missing details their card needs: '
            .$notReady->map(fn (Student $s) => $s->name)->implode(', ').'.');

        return $students;
    }
}
