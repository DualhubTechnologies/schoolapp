<?php

namespace App\Filament\App\Pages;

use App\Filament\Concerns\GatedByModule;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\Term;
use App\Models\TransportRoute;
use App\Services\Transport\TransportLedger;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * What the van has collected for a term, route by route, and who still
 * owes. Payments are counted transport-first (see TransportLedger).
 */
class TransportCollections extends Page
{
    use GatedByModule;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Transport';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Transport collections';

    protected static ?string $slug = 'transport-collections';

    protected string $view = 'filament.app.pages.transport-collections';

    public ?int $termId = null;

    public ?int $routeId = null;

    /** all | owing | paid */
    public string $show = 'all';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->school_id !== null && parent::shouldRegisterNavigation();
    }

    public function mount(): void
    {
        $this->termId = Term::current(auth()->user()?->school_id)?->getKey();
    }

    /** @return Collection<int, string> */
    public function termOptions(): Collection
    {
        return Term::where('school_id', auth()->user()?->school_id)->with('academicYear')->get()
            ->sortByDesc(fn (Term $term): string => $term->sortKey())
            ->mapWithKeys(fn (Term $term): array => [$term->id => $term->label()]);
    }

    /** @return Collection<int, string> */
    public function routeOptions(): Collection
    {
        return TransportRoute::where('school_id', auth()->user()?->school_id)->orderBy('name')->pluck('name', 'id');
    }

    /**
     * One row per learner billed for the van this term.
     *
     * @return Collection<int, array{student: Student, route: string, charged: float, paid: float, owed: float}>
     */
    #[Computed]
    public function rows(): Collection
    {
        if (! $this->termId) {
            return collect();
        }

        $ledger = app(TransportLedger::class);

        return StudentCharge::query()
            ->with(['student.schoolClass', 'student.guardian', 'transportRoute'])
            ->where('school_id', auth()->user()?->school_id)
            ->where('term_id', $this->termId)
            ->whereNotNull('transport_route_id')
            ->when($this->routeId, fn ($query, $routeId) => $query->where('transport_route_id', $routeId))
            ->get()
            ->unique('student_id')
            ->map(fn (StudentCharge $charge): ?array => $this->row($charge, $ledger))
            ->filter(fn (?array $row): bool => $row !== null && match ($this->show) {
                'owing' => $row['owed'] > 0,
                'paid' => $row['owed'] <= 0,
                default => true,
            })
            ->sortBy(fn (array $row): string => $row['route'].'|'.$row['student']->name)
            ->values();
    }

    /**
     * @return array{student: Student, route: string, charged: float, paid: float, owed: float}|null
     */
    protected function row(StudentCharge $charge, TransportLedger $ledger): ?array
    {
        $student = $charge->student;

        if (! $student) {
            return null;
        }

        $term = $ledger->forStudent($student)['terms'][$this->termId] ?? ['charged' => 0.0, 'paid' => 0.0];

        return [
            'student' => $student,
            'route' => $charge->transportRoute->name ?? 'Route removed',
            'charged' => $term['charged'],
            'paid' => $term['paid'],
            'owed' => max(0.0, $term['charged'] - $term['paid']),
        ];
    }

    /**
     * Totals per route, then for the whole school.
     *
     * @return array{routes: Collection<string, array{learners: int, charged: float, paid: float, owed: float}>, total: array{learners: int, charged: float, paid: float, owed: float}}
     */
    #[Computed]
    public function summary(): array
    {
        $sum = fn (Collection $rows): array => [
            'learners' => $rows->count(),
            'charged' => (float) $rows->sum('charged'),
            'paid' => (float) $rows->sum('paid'),
            'owed' => (float) $rows->sum('owed'),
        ];

        $rows = $this->rows();

        return [
            'routes' => $rows->groupBy('route')->map($sum),
            'total' => $sum($rows),
        ];
    }
}
