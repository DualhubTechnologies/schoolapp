<?php

namespace App\Filament\Pages;

use App\Models\Assessment;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Services\Academics\MarkSheetProgress;
use App\Services\Academics\MarkSheets;
use App\Support\AcademicAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * For the Director of Studies: every mark sheet of an exam at a glance --
 * which are not started, being entered, complete, submitted or approved,
 * and by whom -- with the submitted ones approved in one go.
 *
 * @property-read Collection<int, MarkSheetProgress> $rows
 * @property-read Assessment|null $assessment
 */
class MarksProgress extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Exams & Results';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Marks Progress';

    protected string $view = 'filament.pages.marks-progress';

    public ?int $assessmentId = null;

    public ?int $classId = null;

    public const STATUS_LABELS = [
        'not_started' => 'Not started',
        'in_progress' => 'Being entered',
        'complete' => 'Complete, not submitted',
        'submitted' => 'Submitted',
        'approved' => 'Approved',
    ];

    public static function canAccess(): bool
    {
        return AcademicAccess::manages();
    }

    public function mount(): void
    {
        $this->assessmentId = request()->integer('assessment') ?: $this->assessmentOptions()->keys()->first();
    }

    public function updatedAssessmentId(): void
    {
        unset($this->assessment, $this->rows);
    }

    public function updatedClassId(): void
    {
        unset($this->rows);
    }

    /**
     * This term's exams first, newest first.
     *
     * @return Collection<int, non-falsy-string>
     */
    public function assessmentOptions(): Collection
    {
        $current = Term::current()?->getKey();

        return Assessment::where('school_id', auth()->user()?->school_id)
            ->with('term.academicYear')
            ->get()
            ->toBase()
            ->sortBy([
                fn ($a, $b) => ($b->term_id === $current) <=> ($a->term_id === $current),
                fn ($a, $b) => ($b->term?->sortKey() ?? '') <=> ($a->term?->sortKey() ?? ''),
                fn ($a, $b) => $b->sort_order <=> $a->sort_order,
            ])
            ->mapWithKeys(fn (Assessment $a): array => [$a->id => (string) $a->name.' — '.($a->term?->label() ?? '')]);
    }

    /** @return Collection<int, string> */
    public function classOptions(): Collection
    {
        return SchoolClass::where('school_id', auth()->user()?->school_id)->orderBy('level')->orderBy('name')->pluck('name', 'id');
    }

    #[Computed]
    public function assessment(): ?Assessment
    {
        return $this->assessmentId
            ? Assessment::where('school_id', auth()->user()?->school_id)->find($this->assessmentId)
            : null;
    }

    /**
     * @return Collection<int, MarkSheetProgress>
     */
    #[Computed]
    public function rows(): Collection
    {
        $assessment = $this->assessment;

        return $assessment ? app(MarkSheets::class)->progress($assessment, $this->classId) : collect();
    }

    /**
     * How many sheets are at each stage, in STATUS_LABELS order.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = $this->rows->countBy(fn (MarkSheetProgress $row) => $row->status);

        return collect(self::STATUS_LABELS)->map(fn ($label, $status) => (int) ($counts[$status] ?? 0))->all();
    }

    public function approveAllAction(): Action
    {
        return Action::make('approveAll')
            ->label(fn (): string => 'Approve all submitted ('.$this->counts()['submitted'].')')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (): bool => $this->counts()['submitted'] > 0 && ! $this->assessment?->isLocked())
            ->requiresConfirmation()
            ->modalDescription('Every submitted mark sheet shown here becomes final. You can still reopen one from its mark sheet.')
            ->action(function (): void {
                $approved = 0;

                foreach ($this->rows->filter(fn (MarkSheetProgress $row) => $row->status === 'submitted') as $row) {
                    $row->sheet->approve(auth()->user());
                    $approved++;
                }

                unset($this->rows);
                Notification::make()->title("{$approved} mark ".str('sheet')->plural($approved).' approved')->success()->send();
            });
    }
}
