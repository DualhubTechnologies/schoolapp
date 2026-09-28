<?php

namespace App\Services;

use App\Concerns\EmbedsImages;
use App\Models\AcademicYear;
use App\Models\IdCardTemplate;
use App\Models\School;
use App\Models\Student;
use App\Support\PrivateFiles;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Builds student ID cards from the school's template: the front carries
 * the learner's own details, the back is the same for every card (school
 * contacts, rules, signature). Preview, the card-printer view and the PDF
 * all read from here, so they always agree on what a card shows and on
 * whether it is ready to print.
 */
class IdCardService
{
    use EmbedsImages;

    /** @var array<int, AcademicYear|null> school_id => current academic year, memoised per batch */
    protected array $currentYears = [];

    /**
     * What the front needs and cannot do without. Nothing here is required
     * to create a student, so a card is often not printable the day a
     * learner is admitted -- the preview says why, and printing stays
     * blocked until it is.
     *
     * @return array<string, bool> label => present
     */
    public function checklist(Student $student): array
    {
        return [
            'photo' => filled($student->photo),
            'student number' => filled($student->admission_no),
            'class' => filled($student->school_class_id),
            'date of birth' => filled($student->date_of_birth),
            'sex' => filled($student->gender),
            'parent / guardian phone' => filled($student->guardian?->phone),
        ];
    }

    /** @return list<string> the still-missing labels, in checklist order */
    public function missing(Student $student): array
    {
        return array_keys(array_filter($this->checklist($student), fn (bool $present): bool => ! $present));
    }

    public function isReady(Student $student): bool
    {
        return $this->missing($student) === [];
    }

    /**
     * The card number: school code / year of printing / learner, e.g.
     * "SH84914/26/00142". The same learner keeps the same number all
     * year, so a reprinted card matches the one it replaces.
     */
    public function cardNumber(Student $student, CarbonInterface $issuedOn): string
    {
        $code = $student->school?->unique_code ?: 'SCH'.$student->school_id;

        return strtoupper($code).'/'.$issuedOn->format('y').'/'.str_pad((string) $student->id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * The learner's details for the front, label => value, in print order.
     * Optional details (house, residence, LIN) appear only when recorded.
     *
     * @return array<string, string>
     */
    public function frontFields(Student $student): array
    {
        $class = $student->schoolClass?->name;

        return array_filter([
            'Student No.' => (string) $student->admission_no,
            'Class' => $class ? $class.($student->section ? ' '.$student->section->name : '') : '',
            'Sex' => Student::GENDERS[$student->gender] ?? '',
            'Date of Birth' => $student->date_of_birth?->format('d/m/Y') ?? '',
            'House' => (string) $student->house?->name,
            'Residence' => (string) $student->residencyType?->name,
            'LIN' => (string) $student->lin,
            'Parent Tel.' => (string) $student->guardian?->phone,
        ], fn (string $value): bool => $value !== '');
    }

    /**
     * Everything the front template reads for one learner.
     *
     * @return array<string, mixed>
     */
    public function cardData(Student $student, IdCardTemplate $template, ?CarbonInterface $issuedOn = null): array
    {
        $student->loadMissing(['school', 'guardian', 'schoolClass', 'section', 'house', 'residencyType']);
        $issuedOn ??= now();

        return [
            'student' => $student,
            'fields' => $this->frontFields($student),
            'cardNumber' => $this->cardNumber($student, $issuedOn),
            'issuedOn' => $issuedOn,
            'expiresOn' => $template->expiresOn($issuedOn, $this->currentYear($student->school_id)),
            'photoPath' => PrivateFiles::dataUri($student->photo),
            'missing' => $this->missing($student),
            'ready' => $this->isReady($student),
        ];
    }

    /**
     * Front data for a set of learners, in the order given.
     *
     * @param  Collection<int, Student>  $students
     * @return Collection<int, array<string, mixed>>
     */
    public function cardsFor(Collection $students, IdCardTemplate $template): Collection
    {
        $issuedOn = now();

        return $students->map(fn (Student $student) => $this->cardData($student, $template, $issuedOn));
    }

    /**
     * What every card in the school shares: the header on the front and
     * the whole of the back.
     *
     * @return array<string, mixed>
     */
    public function schoolData(School $school, IdCardTemplate $template): array
    {
        return [
            'school' => $school,
            'logoPath' => $this->embeddableImage($school->logo),
            'signaturePath' => PrivateFiles::dataUri($school->hm_signature),
            'notes' => $template->noteLines(),
        ];
    }

    /**
     * The template's colours and shapes, ready for the stylesheet.
     *
     * @return array<string, string>
     */
    public function design(IdCardTemplate $template): array
    {
        $primary = IdCardTemplate::hex($template->primary_color, IdCardTemplate::DEFAULTS['primary_color']);
        $accent = IdCardTemplate::hex($template->accent_color, IdCardTemplate::DEFAULTS['accent_color']);

        return [
            'orientation' => $template->isPortrait() ? 'portrait' : 'landscape',
            'primary' => $primary,
            'accent' => $accent,
            'onPrimary' => IdCardTemplate::textOn($primary),
            'onAccent' => IdCardTemplate::textOn($accent),
            'waveTop' => $this->wave($primary, $accent, false),
            'waveBottom' => $this->wave($primary, $accent, true),
        ];
    }

    /**
     * The curved edge under the header (or above the footer), as an SVG
     * data URI in the school's colours: a band of the main colour with an
     * accent line following the curve.
     */
    protected function wave(string $primary, string $accent, bool $flip): string
    {
        $shape = $flip
            ? '<path d="M0 12 V6 C30 -1 70 13 100 4 V12 Z" fill="'.$primary.'"/><path d="M0 6 C30 -1 70 13 100 4" fill="none" stroke="'.$accent.'" stroke-width="1.6"/>'
            : '<path d="M0 0 H100 V6 C70 14 30 0 0 8 Z" fill="'.$primary.'"/><path d="M0 8 C30 0 70 14 100 6" fill="none" stroke="'.$accent.'" stroke-width="1.6"/>';

        return 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 12" preserveAspectRatio="none" width="100" height="12">'.$shape.'</svg>');
    }

    protected function currentYear(int $schoolId): ?AcademicYear
    {
        if (! array_key_exists($schoolId, $this->currentYears)) {
            $this->currentYears[$schoolId] = AcademicYear::current($schoolId);
        }

        return $this->currentYears[$schoolId];
    }
}
