<?php

namespace App\Services;

use App\Concerns\EmbedsImages;
use App\Models\AcademicYear;
use App\Models\IdCardTemplate;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Support\PrivateFiles;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds student and staff ID cards from the school's template: the front
 * carries the holder's own details, the back is the same for every card
 * (school contacts, rules, signature). Preview, the card-printer view and
 * the PDF all read from here, so they always agree on what a card shows
 * and on whether it is ready to print.
 */
class IdCardService
{
    use EmbedsImages;

    /** @var array<int, AcademicYear|null> school_id => current academic year, memoised per batch */
    protected array $currentYears = [];

    /**
     * What the front needs and cannot do without. None of it is required to
     * create a student or staff record, so a card is often not printable
     * the day someone joins -- the preview says why, and printing stays
     * blocked until it is.
     *
     * @return array<string, bool> label => present
     */
    public function checklist(Student|Staff $holder): array
    {
        if ($holder instanceof Staff) {
            return [
                'photo' => filled($holder->photo),
                'staff number' => filled($holder->staff_no),
                'designation' => filled($holder->position),
                'sex' => filled($holder->gender),
                'phone' => filled($holder->phone),
            ];
        }

        return [
            'photo' => filled($holder->photo),
            'student number' => filled($holder->admission_no),
            'class' => filled($holder->school_class_id),
            'date of birth' => filled($holder->date_of_birth),
            'sex' => filled($holder->gender),
            'parent / guardian phone' => filled($holder->guardian?->phone),
        ];
    }

    /** @return list<string> the still-missing labels, in checklist order */
    public function missing(Student|Staff $holder): array
    {
        return array_keys(array_filter($this->checklist($holder), fn (bool $present): bool => ! $present));
    }

    public function isReady(Student|Staff $holder): bool
    {
        return $this->missing($holder) === [];
    }

    /**
     * The card number: school code / year of printing / holder, e.g.
     * "SH84914/26/00142" for a learner and "SH84914/26/S0042" for staff.
     * The same person keeps the same number all year, so a reprinted card
     * matches the one it replaces.
     */
    public function cardNumber(Student|Staff $holder, CarbonInterface $issuedOn): string
    {
        $code = (string) $holder->school?->getAttribute('unique_code') ?: 'SCH'.$holder->school_id;
        $serial = $holder instanceof Staff
            ? 'S'.str_pad((string) $holder->id, 4, '0', STR_PAD_LEFT)
            : str_pad((string) $holder->id, 5, '0', STR_PAD_LEFT);

        return strtoupper($code).'/'.$issuedOn->format('y').'/'.$serial;
    }

    /**
     * The holder's details for the front, label => value, in print order.
     * Optional details appear only when recorded.
     *
     * @return array<string, string>
     */
    public function frontFields(Student|Staff $holder): array
    {
        $dateOfBirth = $holder->date_of_birth ? Carbon::parse($holder->date_of_birth)->format('d/m/Y') : '';

        if ($holder instanceof Staff) {
            $fields = [
                'Staff No.' => (string) $holder->staff_no,
                'Designation' => (string) $holder->position,
                'Department' => (string) $holder->department,
                'Sex' => Staff::GENDERS[$holder->gender] ?? '',
                'Date of Birth' => $dateOfBirth,
                'NIN' => (string) $holder->nin,
                'Tel.' => (string) $holder->phone,
            ];
        } else {
            $class = $holder->schoolClass?->name;
            $fields = [
                'Student No.' => (string) $holder->admission_no,
                'Class' => $class ? $class.($holder->section ? ' '.$holder->section->name : '') : '',
                'Sex' => Student::GENDERS[$holder->gender] ?? '',
                'Date of Birth' => $dateOfBirth,
                'House' => (string) $holder->house?->getAttribute('name'),
                'Residence' => (string) $holder->residencyType?->getAttribute('name'),
                'LIN' => (string) $holder->lin,
                'Parent Tel.' => (string) $holder->guardian?->phone,
            ];
        }

        return array_filter($fields, fn (string $value): bool => $value !== '');
    }

    /**
     * Everything the front template reads for one card holder.
     *
     * @return array<string, mixed>
     */
    public function cardData(Student|Staff $holder, IdCardTemplate $template, ?CarbonInterface $issuedOn = null): array
    {
        $holder->loadMissing($holder instanceof Staff
            ? ['school']
            : ['school', 'guardian', 'schoolClass', 'section', 'house', 'residencyType']);
        $issuedOn ??= now();

        return [
            'holder' => $holder,
            'name' => (string) $holder->name,
            'role' => $holder instanceof Staff ? 'STAFF' : 'STUDENT',
            'fields' => $this->frontFields($holder),
            'cardNumber' => $this->cardNumber($holder, $issuedOn),
            'issuedOn' => $issuedOn,
            'expiresOn' => $template->expiresOn($issuedOn, $this->currentYear($holder->school_id)),
            'photoPath' => PrivateFiles::dataUri($holder->photo),
            'missing' => $this->missing($holder),
            'ready' => $this->isReady($holder),
        ];
    }

    /**
     * Front data for a set of card holders, in the order given.
     *
     * @param  Collection<int, Student>|Collection<int, Staff>  $holders
     * @return Collection<int, array<string, mixed>>
     */
    public function cardsFor(Collection $holders, IdCardTemplate $template): Collection
    {
        $issuedOn = now();

        return $holders->map(fn (Student|Staff $holder) => $this->cardData($holder, $template, $issuedOn));
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
     * A made-up card for the template preview: sample school, sample
     * holder, so a school sees its choices before any real record is
     * ready. Everything the front and back templates read.
     *
     * @return array{card: array<string, mixed>, shared: array<string, mixed>, design: array<string, string>}
     */
    public function sample(IdCardTemplate $template, bool $staff = false): array
    {
        $issuedOn = now();
        $school = new School([
            'name' => 'Sample Secondary School',
            'motto' => 'Learners today, leaders tomorrow',
            'address' => 'P.O. Box 100, Kampala',
            'phone' => '0772 000 000',
            'email' => 'info@sampleschool.ac.ug',
        ]);

        $fields = $staff
            ? ['Staff No.' => 'ST-0012', 'Designation' => 'Teacher', 'Department' => 'Sciences', 'Sex' => 'Male', 'Tel.' => '0772 123 456']
            : ['Student No.' => 'ADM-0001', 'Class' => 'S.1 East', 'Sex' => 'Male', 'Date of Birth' => '14/03/2012', 'Parent Tel.' => '0772 123 456'];

        return [
            'card' => [
                'name' => 'Mugizi Adrian',
                'role' => $staff ? 'STAFF' : 'STUDENT',
                'fields' => $fields,
                'cardNumber' => 'SH00000/'.$issuedOn->format('y').($staff ? '/S0001' : '/00001'),
                'issuedOn' => $issuedOn,
                'expiresOn' => $template->validity === 'months'
                    ? $issuedOn->copy()->addMonths(max(1, $template->validity_months ?: 12))
                    : Carbon::create((int) $issuedOn->format('Y'), 12, 4),
                'photoPath' => null,
            ],
            'shared' => [
                'school' => $school,
                'logoPath' => null,
                'signaturePath' => null,
                'notes' => $template->noteLines(),
            ],
            'design' => $this->design($template),
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
