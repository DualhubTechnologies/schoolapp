<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\SchoolScoped;
use App\Models\SchoolClass;
use App\Models\Student;
use Filament\Widgets\ChartWidget;

/**
 * Active students per class, split by gender.
 */
class StudentsByClassChart extends ChartWidget
{
    use SchoolScoped;

    protected static ?int $sort = 3;

    protected ?string $heading = 'Students by class';

    protected ?string $description = 'Active students, by gender';

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return auth()->user()?->school_id !== null;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $classes = SchoolClass::where('school_id', $this->schoolId())
            ->orderBy('level')
            ->orderBy('name')
            ->pluck('name', 'id');

        $counts = Student::where('school_id', $this->schoolId())
            ->where('status', 'active')
            ->selectRaw('school_class_id, gender, COUNT(*) as n')
            ->groupBy('school_class_id', 'gender')
            ->get()
            ->groupBy('school_class_id');

        $series = fn (?string $gender) => $classes->keys()
            ->map(fn ($id) => (int) ($counts->get($id)?->firstWhere('gender', $gender)?->n ?? 0))
            ->all();

        $datasets = [
            ['label' => 'Male', 'data' => $series('male'), 'backgroundColor' => '#2472c4', 'borderRadius' => 3],
            ['label' => 'Female', 'data' => $series('female'), 'backgroundColor' => '#e0719c', 'borderRadius' => 3],
        ];

        // Only show "Not recorded" when some students lack a gender.
        $unknown = $series(null);
        if (array_sum($unknown) > 0) {
            $datasets[] = ['label' => 'Not recorded', 'data' => $unknown, 'backgroundColor' => '#cbd5e1', 'borderRadius' => 3];
        }

        return [
            'datasets' => $datasets,
            'labels' => $classes->values()->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => [
                'x' => ['stacked' => true, 'grid' => ['display' => false]],
                'y' => ['stacked' => true, 'beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }
}
