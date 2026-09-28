<?php

namespace App\Services;

use App\Concerns\EmbedsImages;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Support\PrivateFiles;
use Illuminate\Support\Collection;

/**
 * Builds the front/back data for a student's ID card, and decides whether
 * there is enough on file to print one. Preview, the browser-print view and
 * the exported PDF all read their card data from here, so the three always
 * agree on what a card looks like and what counts as "ready".
 */
class IdCardService
{
    use EmbedsImages;

    /** school_id => current academic year's name, memoised for a whole class print. */
    protected array $currentYearNames = [];

    /**
     * What the card needs and cannot do without. Nothing here is required
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
            'class' => filled($student->school_class_id),
            'date of birth' => filled($student->date_of_birth),
            'sex' => filled($student->gender),
            'parent / guardian' => filled($student->guardian_id),
            'guardian phone' => filled($student->guardian?->phone),
            'home address' => filled($student->address),
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
     * Card data for one student: everything the front/back templates read,
     * with photos and signature already turned into embeddable data URIs
     * so the same array works for the live preview, the print view and the
     * exported PDF alike.
     *
     * @return array<string, mixed>
     */
    public function cardData(Student $student): array
    {
        $student->loadMissing(['school', 'guardian', 'schoolClass', 'section']);
        $school = $student->school;

        return [
            'student' => $student,
            'school' => $school,
            'guardian' => $student->guardian,
            'validYear' => $this->currentYearName($student->school_id),
            'photoPath' => PrivateFiles::dataUri($student->photo),
            'logoPath' => $this->embeddableImage($school?->logo),
            'signaturePath' => PrivateFiles::dataUri($school?->hm_signature),
            'missing' => $this->missing($student),
            'ready' => $this->isReady($student),
        ];
    }

    /**
     * The school's current academic year name, one query per school no
     * matter how many cards are printed in the same batch.
     */
    protected function currentYearName(int $schoolId): ?string
    {
        if (! array_key_exists($schoolId, $this->currentYearNames)) {
            $this->currentYearNames[$schoolId] = AcademicYear::where('school_id', $schoolId)
                ->where('is_current', true)
                ->value('name');
        }

        return $this->currentYearNames[$schoolId];
    }

    /**
     * Card data for a set of students, in the order given.
     *
     * @param  Collection<int, Student>  $students
     * @return Collection<int, array<string, mixed>>
     */
    public function cardsFor(Collection $students): Collection
    {
        return $students->map(fn (Student $student) => $this->cardData($student));
    }
}
