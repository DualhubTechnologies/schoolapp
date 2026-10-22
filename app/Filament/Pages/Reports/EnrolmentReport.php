<?php

namespace App\Filament\Pages\Reports;

use App\Models\ClassLevel;
use App\Models\ResidencyType;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * How many learners the school has: per class (and stream), boys and
 * girls, each residency (Day, Boarding), and new learners - the figures a
 * head teacher gives the board, the district and EMIS.
 */
class EnrolmentReport extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $title = 'Enrolment Report';

    protected static ?string $slug = 'reports/enrolment';

    protected string $view = 'filament.pages.reports.enrolment';

    /** Which learners: active (default), or another status. */
    public string $status = 'active';

    /** One section of the school (O-Level, A-Level...), or all. */
    public ?int $levelId = null;

    public static function about(): string
    {
        return 'Learners per class and stream: boys and girls, day and boarding, new learners.';
    }

    public function coverage(): string
    {
        $level = $this->levelId ? ClassLevel::whereKey($this->levelId)->value('name') : null;

        return trim(($level ? "{$level} · " : '').(Student::STATUSES[$this->status] ?? 'All').' learners · '.now()->format('j M Y'));
    }

    /** @return array<int, string> */
    public function residencies(): array
    {
        return ResidencyType::where('school_id', auth()->user()?->school_id)->where('is_active', true)->orderBy('id')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    public function levelOptions(): array
    {
        return ClassLevel::where('school_id', auth()->user()?->school_id)->orderBy('sort_order')->pluck('name', 'id')->all();
    }

    /**
     * A row per class (its streams beneath it), then the school total.
     *
     * @return array<int, array{label: string, kind: string, boys: int, girls: int, total: int, residency: array<int, int>, new: int}>
     */
    public function rows(): array
    {
        $schoolId = auth()->user()?->school_id;

        $learners = Student::query()
            ->where('school_id', $schoolId)
            ->where('status', $this->status)
            ->whereNotNull('school_class_id')
            ->get(['id', 'school_class_id', 'section_id', 'gender', 'residency_type_id', 'is_new_learner']);

        $classes = SchoolClass::inOrder($schoolId)
            ->when($this->levelId, fn (Collection $classes) => $classes->where('class_level_id', $this->levelId));

        $streams = Section::where('school_id', $schoolId)->orderBy('name')->get();

        $rows = [];

        foreach ($classes as $class) {
            $inClass = $learners->where('school_class_id', $class->getKey());

            if ($inClass->isEmpty()) {
                continue;
            }

            $rows[] = $this->count($class->name, 'class', $inClass);

            foreach ($streams->where('school_class_id', $class->getKey()) as $section) {
                $inSection = $inClass->where('section_id', $section->getKey());

                if ($inSection->isNotEmpty()) {
                    $rows[] = $this->count($class->name.' '.$section->name, 'stream', $inSection);
                }
            }
        }

        $counted = $learners->whereIn('school_class_id', $classes->modelKeys());

        if ($counted->isNotEmpty()) {
            $rows[] = $this->count('Total', 'total', $counted);
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Student>  $learners
     * @return array{label: string, kind: string, boys: int, girls: int, total: int, residency: array<int, int>, new: int}
     */
    protected function count(string $label, string $kind, Collection $learners): array
    {
        return [
            'label' => $label,
            'kind' => $kind,
            'boys' => $learners->where('gender', 'male')->count(),
            'girls' => $learners->where('gender', 'female')->count(),
            'total' => $learners->count(),
            'residency' => collect($this->residencies())->map(fn (string $name, int $id): int => $learners->where('residency_type_id', $id)->count())->all(),
            'new' => $learners->where('is_new_learner', true)->count(),
        ];
    }

    public function sheet(): array
    {
        $residencies = $this->residencies();

        return [
            ['Class', 'Boys', 'Girls', 'Total', ...array_values($residencies), 'New learners'],
            array_map(fn (array $row): array => [
                $row['kind'] === 'stream' ? '   '.$row['label'] : $row['label'],
                $row['boys'], $row['girls'], $row['total'],
                ...array_values($row['residency']),
                $row['new'],
            ], $this->rows()),
        ];
    }
}
