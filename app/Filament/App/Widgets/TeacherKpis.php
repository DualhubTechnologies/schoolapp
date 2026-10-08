<?php

namespace App\Filament\App\Widgets;

use App\Filament\Pages\ClassResults;
use App\Filament\Pages\EnterMarks;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Services\Dashboard\TeacherLoad;
use App\Support\AcademicAccess;

/** Teacher: what they teach, whom, and how much marking is left. */
class TeacherKpis extends KpiCards
{
    public function cards(): array
    {
        $staffId = AcademicAccess::staffId();

        if (! $staffId) {
            return [
                static::card('amber', 'heroicon-o-link', 'Login not linked to staff', '—',
                    'Ask the school administrator to link your login to your staff record.'),
            ];
        }

        $rows = TeacherLoad::rows((int) $this->schoolId(), $staffId);
        $learners = $rows->pluck('studentIds')->flatten()->unique()->count();
        $expected = $rows->filter(fn ($r) => $r['assessment'])->sum('learners');
        $entered = $rows->sum('entered');
        $rate = static::percent($entered, $expected);
        $streams = Section::whereIn('id', array_keys(AcademicAccess::classTeacherStreams()))
            ->with('schoolClass')->get()->toBase()
            ->map(fn (Section $s) => trim(($s->schoolClass?->name ?? '').' '.$s->name))
            ->merge(SchoolClass::whereIn('id', AcademicAccess::classTeacherWholeClasses())->pluck('name'));

        return [
            static::card('blue', 'heroicon-o-book-open', 'Subjects I teach', (string) $rows->count(),
                $rows->pluck('class.name')->unique()->count().' '.str('class')->plural($rows->pluck('class.name')->unique()->count()).' this term',
                EnterMarks::getUrl()),
            static::card('violet', 'heroicon-o-users', 'My learners', number_format($learners),
                'Across all my classes', ClassResults::canAccess() ? ClassResults::getUrl() : null),
            static::card($rate === 100 ? 'emerald' : 'amber', 'heroicon-o-pencil-square', 'Marks entered',
                $rate === null ? '—' : static::percentText($entered, $expected),
                $expected ? number_format($entered).' of '.number_format($expected).' for the current exam' : 'No open exam this term',
                EnterMarks::getUrl(), $rate),
            static::card('teal', 'heroicon-o-home-modern', 'Class teacher of',
                $streams->isEmpty() ? '—' : (string) $streams->count(),
                $streams->isEmpty() ? 'No class assigned' : $streams->take(3)->implode(', ')),
        ];
    }
}
