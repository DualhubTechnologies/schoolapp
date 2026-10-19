<?php

namespace App\Filament\Support;

use App\Models\Staff;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;

/**
 * Find a learner or a member of staff of the user's school by name or
 * number, for the search boxes in the library and the sick bay.
 */
class PeopleSearch
{
    /** @return array<int, string> id => "Nakato Sarah · S.2 East · DHS/2026/0014" */
    public static function learners(string $search): array
    {
        return Student::query()
            ->with(['schoolClass', 'section'])
            ->where('school_id', auth()->user()?->school_id)
            ->where('status', 'active')
            ->where(fn (Builder $q) => $q->where('name', 'like', "%{$search}%")->orWhere('admission_no', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(30)
            ->get()
            ->mapWithKeys(fn (Student $s): array => [$s->id => static::learnerText($s)])
            ->all();
    }

    public static function learnerLabel(mixed $id): ?string
    {
        $student = Student::with(['schoolClass', 'section'])->where('school_id', auth()->user()?->school_id)->whereKey($id)->first();

        return $student ? static::learnerText($student) : null;
    }

    /** @return array<int, string> id => "Okello James · Teacher" */
    public static function staff(string $search): array
    {
        return Staff::query()
            ->where('school_id', auth()->user()?->school_id)
            ->where(fn (Builder $q) => $q->where('name', 'like', "%{$search}%")->orWhere('staff_no', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(30)
            ->get()
            ->mapWithKeys(fn (Staff $s): array => [$s->id => trim($s->name.' · '.$s->position, ' ·')])
            ->all();
    }

    public static function staffLabel(mixed $id): ?string
    {
        $staff = Staff::where('school_id', auth()->user()?->school_id)->whereKey($id)->first();

        return $staff ? trim($staff->name.' · '.$staff->position, ' ·') : null;
    }

    protected static function learnerText(Student $s): string
    {
        $class = trim(($s->schoolClass->name ?? '').' '.($s->section->name ?? ''));

        return collect([$s->name, $class, $s->admission_no])->filter()->implode(' · ');
    }
}
