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

    protected ?string $icon = 'heroicon-o-user-group';

    protected ?string $iconColor = 'info';

    protected string $view = 'filament.widgets.branded-chart-widget';

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

        // Gender compared in lower case: "Male" from an import counts as male.
        $counts = Student::where('school_id', $this->schoolId())
            ->where('status', 'active')
            ->selectRaw('school_class_id, LOWER(gender) as g, COUNT(*) as n')
            ->groupBy('school_class_id', 'g')
            ->get()
            ->groupBy('school_class_id');

        $series = fn (?string $gender) => $classes->keys()
            ->map(fn ($id) => (int) ($counts->get($id)?->first(fn (Student $row) => $row->getAttribute('g') === $gender)?->getAttribute('n') ?? 0))
            ->all();

        // Boys and girls side by side in each class, so both always show.
        $bar = ['borderRadius' => 5, 'borderSkipped' => false, 'maxBarThickness' => 28, 'categoryPercentage' => 0.7, 'barPercentage' => 0.9];

        $datasets = [
            ['label' => 'Male', 'data' => $series('male'), 'backgroundColor' => '#2472c4', ...$bar],
            ['label' => 'Female', 'data' => $series('female'), 'backgroundColor' => '#c8588a', ...$bar],
        ];

        // Only show "Not recorded" when some students lack a gender.
        $unknown = $series(null);
        if (array_sum($unknown) > 0) {
            $datasets[] = ['label' => 'Not recorded', 'data' => $unknown, 'backgroundColor' => '#cbd5e1', ...$bar];
        }

        // Each class with its total: "S.1 (84)".
        $totals = $classes->keys()->map(fn ($id) => (int) ($counts->get($id)?->sum('n') ?? 0));

        return [
            'datasets' => $datasets,
            'labels' => $classes->values()->map(fn ($name, $i) => "{$name} ({$totals[$i]})")->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['position' => 'bottom', 'labels' => ['usePointStyle' => true, 'pointStyle' => 'circle', 'padding' => 18]],
                'tooltip' => ['mode' => 'index', 'intersect' => false],
            ],
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'scales' => [
                'x' => ['stacked' => false, 'grid' => ['display' => false], 'ticks' => ['font' => ['weight' => '600']]],
                'y' => ['stacked' => false, 'beginAtZero' => true, 'grace' => '10%', 'ticks' => ['precision' => 0], 'border' => ['display' => false], 'grid' => ['color' => '#eef2f7']],
            ],
        ];
    }
}
