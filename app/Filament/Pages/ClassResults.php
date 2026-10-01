<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ChoosesExam;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Term;
use App\Services\Academics\ResultsCalculator;
use App\Support\AcademicAccess;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The class broadsheet for a term: every student's subject scores and
 * grades, the overall result (aggregate & division, average & level, or
 * points), positions, and a subject-by-subject analysis.
 */
class ClassResults extends Page
{
    use ChoosesExam;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|\UnitEnum|null $navigationGroup = 'Exams & Results';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Results & Broadsheet';

    protected static ?string $navigationLabel = 'Results';

    protected string $view = 'filament.pages.class-results';

    public ?int $termId = null;

    public ?int $classId = null;

    public ?int $sectionId = null;

    public function mount(): void
    {
        $this->termId = request()->integer('term') ?: Term::current()?->getKey();
        $this->classId = request()->integer('class') ?: null;
    }

    public static function canAccess(): bool
    {
        return AcademicAccess::teaches();
    }

    public function updatedTermId(): void
    {
        $this->examId = null;
    }

    public function updatedClassId(): void
    {
        $this->sectionId = null;
    }

    /** @return Collection<int, string> */
    public function termOptions(): Collection
    {
        return Term::where('school_id', auth()->user()?->school_id)
            ->with('academicYear')
            ->get()
            ->sortByDesc(fn (Term $t) => $t->sortKey())
            ->mapWithKeys(fn (Term $t) => [$t->id => $t->label()]);
    }

    /** @return Collection<int, string> */
    public function classOptions(): Collection
    {
        return SchoolClass::where('school_id', auth()->user()?->school_id)->orderBy('level')->orderBy('name')->pluck('name', 'id');
    }

    /** @return Collection<int, string> */
    public function sectionOptions(): Collection
    {
        return $this->classId ? Section::where('school_class_id', $this->classId)->orderBy('name')->pluck('name', 'id') : collect();
    }

    #[Computed]
    public function results(): ?array
    {
        $class = $this->classId ? SchoolClass::where('school_id', auth()->user()?->school_id)->find($this->classId) : null;
        $term = $this->termId ? Term::where('school_id', auth()->user()?->school_id)->find($this->termId) : null;

        return $class && $term ? app(ResultsCalculator::class)->forClass($class, $term, $this->sectionId, $this->chosenExamId()) : null;
    }

    /**
     * The broadsheet as a spreadsheet file (CSV opens in Excel).
     */
    public function exportCsv(): ?StreamedResponse
    {
        $r = $this->results;

        if (! $r) {
            return null;
        }

        $subjects = $r['subjects']->filter(fn ($s) => isset($r['subject_stats'][$s->id]));
        $overall = $this->overallColumns($r['curriculum']);
        $filename = str($r['class']->name.' '.$r['term']->label().' '.($r['exam']->name ?? '').' results')->slug().'.csv';

        return response()->streamDownload(function () use ($r, $subjects, $overall) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['Position', 'Adm. No.', 'Name', 'Stream'], $subjects->map(fn ($s) => $s->label())->all(), ['Total', 'Average'], array_values($overall)));

            foreach ($r['rows'] as $row) {
                fputcsv($out, array_merge(
                    [$row['position'], $row['student']->admission_no, $row['student']->name, $row['student']->section?->name],
                    $subjects->map(fn ($s) => isset($row['subjects'][$s->id]) ? $row['subjects'][$s->id]['final'].' '.$row['subjects'][$s->id]['grade'] : '')->all(),
                    [$row['total'], $row['average']],
                    collect(array_keys($overall))->map(fn ($k) => $row[$k] ?? '')->all(),
                ));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<string, string> row key => heading
     */
    public function overallColumns(?string $curriculum): array
    {
        return match ($curriculum) {
            'primary' => ['aggregate' => 'Agg.', 'division' => 'Div.'],
            'a_level' => ['result_code' => 'Result', 'points' => 'Points'],
            default => ['overall_grade' => 'Level', 'overall_descriptor' => 'Descriptor'],
        };
    }
}
