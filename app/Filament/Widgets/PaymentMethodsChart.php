<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\SchoolScoped;
use App\Models\StudentPayment;
use Filament\Widgets\ChartWidget;

/**
 * How this term's fees were paid: cash, bank, mobile money, SchoolPay.
 */
class PaymentMethodsChart extends ChartWidget
{
    use SchoolScoped;

    protected static ?int $sort = 4;

    protected ?string $heading = 'How fees are paid';

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return static::userHandlesFees();
    }

    public function getDescription(): ?string
    {
        $term = $this->currentTerm();

        return $term ? 'Amount collected by method, ' . $term->label() : 'Amount collected by method';
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $term = $this->currentTerm();

        $totals = StudentPayment::where('school_id', $this->schoolId())
            ->when($term, fn ($q) => $q->where(function ($q) use ($term) {
                $q->where('term_id', $term->getKey());

                if ($term->start_date && $term->end_date) {
                    $q->orWhere(fn ($q) => $q->whereNull('term_id')
                        ->whereBetween('paid_on', [$term->start_date, $term->end_date]));
                }
            }))
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method');

        $colors = [
            'cash' => '#0f7a68',
            'bank' => '#1a5fa8',
            'mobile_money' => '#d97d0d',
            'schoolpay' => '#6b4f9e',
        ];

        return [
            'datasets' => [[
                'data' => $totals->values()->map(fn ($v) => (float) $v)->all(),
                'backgroundColor' => $totals->keys()->map(fn ($m) => $colors[$m] ?? '#94a3b8')->all(),
                'borderWidth' => 0,
            ]],
            'labels' => $totals->keys()->map(fn ($m) => StudentPayment::METHODS[$m] ?? ucfirst((string) $m))->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '62%',
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }
}
