<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\SchoolScoped;
use App\Models\PayrollPeriod;
use App\Models\SalaryArrear;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\StudentPayment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The headline numbers: students, this term's fees, what is owed, and
 * staffing. Money cards are shown only to fee-handling roles.
 */
class StatsOverview extends StatsOverviewWidget
{
    use SchoolScoped;

    protected static ?int $sort = 1;

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $user = auth()->user();

        if ($user?->hasRole('Super Admin')) {
            return $this->superAdminStats();
        }

        return [
            ...$this->studentStats(),
            ...(static::userHandlesFees() ? $this->feeStats() : []),
            ...$this->staffStats(),
        ];
    }

    protected function superAdminStats(): array
    {
        return [
            Stat::make('Schools', \App\Models\School::count())
                ->description('Registered on the platform')
                ->icon('heroicon-o-building-office-2')
                ->color('primary'),
            Stat::make('Students', Student::where('status', 'active')->count())
                ->description('Active, across all schools')
                ->icon('heroicon-o-academic-cap')
                ->color('info'),
            Stat::make('Users', \App\Models\User::count())
                ->description('Across all schools')
                ->icon('heroicon-o-users')
                ->color('success'),
        ];
    }

    // ── Students ──

    protected function studentStats(): array
    {
        $schoolId = $this->schoolId();
        $term = $this->currentTerm();

        $active = Student::where('school_id', $schoolId)->where('status', 'active');

        $newThisTerm = ($term?->start_date && $term?->end_date)
            ? (clone $active)->whereBetween('admission_date', [$term->start_date, $term->end_date])->count()
            : 0;

        $provisional = (clone $active)->where('enrolment_status', 'provisional')->count();

        return [
            Stat::make('Active students', number_format($active->count()))
                ->description($newThisTerm ? "{$newThisTerm} admitted this term" : 'No new admissions this term')
                ->descriptionIcon($newThisTerm ? 'heroicon-m-arrow-trending-up' : null)
                ->chart($this->admissionsByMonth())
                ->icon('heroicon-o-academic-cap')
                ->color('primary'),

            Stat::make('Provisional enrolments', number_format($provisional))
                ->description($provisional ? 'Awaiting payment or confirmation' : 'Everyone is confirmed')
                ->icon('heroicon-o-clock')
                ->color($provisional ? 'warning' : 'success'),
        ];
    }

    /**
     * Admissions in each of the last six months, for the sparkline.
     */
    protected function admissionsByMonth(): array
    {
        $from = now()->startOfMonth()->subMonths(5);

        $counts = Student::where('school_id', $this->schoolId())
            ->where('admission_date', '>=', $from)
            ->selectRaw("DATE_FORMAT(admission_date, '%Y-%m') as ym, COUNT(*) as n")
            ->groupBy('ym')
            ->pluck('n', 'ym');

        return collect(range(0, 5))
            ->map(fn ($i) => (int) ($counts[$from->copy()->addMonths($i)->format('Y-m')] ?? 0))
            ->all();
    }

    // ── Fees ──

    protected function feeStats(): array
    {
        $schoolId = $this->schoolId();
        $term = $this->currentTerm();

        $billed = $term
            ? (float) StudentCharge::where('school_id', $schoolId)
                ->where('term_id', $term->getKey())
                ->sum(DB::raw('amount - discount_amount'))
            : 0.0;

        $collected = $term ? (float) $this->paymentsInTerm()->sum('amount') : 0.0;
        $rate = $billed > 0 ? round($collected / $billed * 100) : null;

        [$owed, $debtors] = $this->outstanding();

        return [
            Stat::make('Billed this term', static::shortMoney($billed))
                ->description($term ? $term->label() : 'No current term set')
                ->icon('heroicon-o-document-text')
                ->color('info'),

            Stat::make('Collected this term', static::shortMoney($collected))
                ->description($rate === null ? 'Nothing billed yet' : "{$rate}% of the amount billed")
                ->descriptionIcon($rate !== null && $rate >= 50 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart($this->collectionsByWeek())
                ->icon('heroicon-o-banknotes')
                ->color(match (true) {
                    $rate === null => 'gray',
                    $rate >= 75 => 'success',
                    $rate >= 40 => 'warning',
                    default => 'danger',
                }),

            Stat::make('Outstanding fees', static::shortMoney($owed))
                ->description($debtors ? number_format($debtors) . ' students owe, all terms' : 'No outstanding balances')
                ->icon('heroicon-o-exclamation-circle')
                ->color($owed > 0 ? 'danger' : 'success'),
        ];
    }

    /**
     * Payments for the current term: tagged with it, or (for untagged
     * payments) made between its start and end dates.
     */
    protected function paymentsInTerm()
    {
        $term = $this->currentTerm();

        return StudentPayment::where('school_id', $this->schoolId())
            ->where(function ($q) use ($term) {
                $q->where('term_id', $term->getKey());

                if ($term->start_date && $term->end_date) {
                    $q->orWhere(fn ($q) => $q->whereNull('term_id')
                        ->whereBetween('paid_on', [$term->start_date, $term->end_date]));
                }
            });
    }

    /**
     * Money collected in each of the last eight weeks, for the sparkline.
     */
    protected function collectionsByWeek(): array
    {
        $from = now()->startOfWeek()->subWeeks(7);

        $payments = StudentPayment::where('school_id', $this->schoolId())
            ->where('paid_on', '>=', $from)
            ->get(['amount', 'paid_on']);

        return collect(range(0, 7))
            ->map(function ($i) use ($from, $payments) {
                $start = $from->copy()->addWeeks($i);

                return (float) $payments
                    ->filter(fn ($p) => Carbon::parse($p->paid_on)->betweenIncluded($start, $start->copy()->endOfWeek()))
                    ->sum('amount');
            })
            ->all();
    }

    /**
     * Total owed by students in debit, and how many of them. Students in
     * credit do not offset what others owe.
     *
     * @return array{0: float, 1: int}
     */
    protected function outstanding(): array
    {
        $schoolId = $this->schoolId();

        $charged = StudentCharge::where('school_id', $schoolId)
            ->groupBy('student_id')
            ->selectRaw('student_id, SUM(amount - discount_amount) as total')
            ->pluck('total', 'student_id');

        $paid = StudentPayment::where('school_id', $schoolId)
            ->groupBy('student_id')
            ->selectRaw('student_id, SUM(amount) as total')
            ->pluck('total', 'student_id');

        $balances = $charged
            ->map(fn ($total, $studentId) => (float) $total - (float) ($paid[$studentId] ?? 0))
            ->filter(fn ($balance) => $balance > 0);

        return [(float) $balances->sum(), $balances->count()];
    }

    // ── Staff ──

    protected function staffStats(): array
    {
        $schoolId = $this->schoolId();

        $activeStaff = Staff::where('school_id', $schoolId)->where('status', 'active')->count();

        $latestPayroll = PayrollPeriod::where('school_id', $schoolId)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();

        $pendingArrears = SalaryArrear::where('school_id', $schoolId)
            ->where('status', 'pending')
            ->count();

        return [
            Stat::make('Active staff', number_format($activeStaff))
                ->description($latestPayroll
                    ? "Payroll {$latestPayroll->period_label}: " . static::shortMoney((float) $latestPayroll->total_net) . ' net · ' . $latestPayroll->status
                    : 'No payroll run yet')
                ->icon('heroicon-o-user-group')
                ->color('success'),

            ...($pendingArrears > 0 ? [
                Stat::make('Salary arrears', number_format($pendingArrears))
                    ->description('Pending review')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger'),
            ] : []),
        ];
    }
}
