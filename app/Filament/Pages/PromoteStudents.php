<?php

namespace App\Filament\Pages;

use App\Models\AcademicYear;
use App\Models\Promotion;
use App\Models\PromotionRule;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Services\Academics\PromotionAdvisor;
use App\Services\Academics\PromotionService;
use App\Services\Academics\ResultsCalculator;
use App\Support\AcademicAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * End of year: move every class up, class by class, with a decision per
 * student (promote, repeat, completed, left). Every run can be undone.
 */
class PromoteStudents extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static string|\UnitEnum|null $navigationGroup = 'Students';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Year-end Promotion';

    protected static ?string $navigationLabel = 'Promote Students';

    protected string $view = 'filament.pages.promote-students';

    public ?int $classId = null;

    public ?int $targetClassId = null;

    /** @var array<int, string> student id => action */
    public array $decisions = [];

    /** @var array<int, array> student id => PromotionAdvisor recommendation */
    public array $advice = [];

    /** all | attention (probation, repeat, no results) */
    public string $show = 'all';

    public static function canAccess(): bool
    {
        return \App\Support\Modules::allows('promotion');
    }

    protected function service(): PromotionService
    {
        return app(PromotionService::class);
    }

    #[Computed]
    public function year(): ?AcademicYear
    {
        return AcademicYear::current(auth()->user()?->school_id);
    }

    /**
     * Every class with its default move and what is left to do.
     *
     * @return Collection<int, array>
     */
    #[Computed]
    public function overview(): Collection
    {
        $service = $this->service();

        return $service->orderedClasses(auth()->user()->school_id)->map(function (SchoolClass $class) use ($service) {
            $next = $service->nextClass($class);
            $final = $service->isFinalClass($class);

            return [
                'class' => $class,
                'pending' => $service->pendingStudents($class, $this->year)->count(),
                'done' => Promotion::where('from_class_id', $class->getKey())
                    ->where('academic_year_id', $this->year?->getKey())
                    ->whereNull('reversed_at')
                    ->count(),
                'default' => $final ? 'Completed' . ($next ? " (or on to {$next->name})" : '') : ($next ? "→ {$next->name}" : 'No next class'),
            ];
        });
    }

    public function review(int $classId): void
    {
        $class = SchoolClass::where('school_id', auth()->user()->school_id)->findOrFail($classId);
        $service = $this->service();

        $this->classId = $class->getKey();
        $this->targetClassId = $service->nextClass($class)?->getKey();
        $this->show = 'all';
        $final = $service->isFinalClass($class);

        // The rules' recommendation is each student's starting decision.
        // Leavers' classes (P7, S4, S6) still start as "Completed".
        $this->advice = app(PromotionAdvisor::class)->advise($class, $this->year);

        $this->decisions = $service->pendingStudents($class, $this->year)
            ->mapWithKeys(fn ($s) => [$s->id => $final ? 'complete' : match ($this->advice[$s->id]['recommendation'] ?? 'promote') {
                'probation' => 'probation',
                'repeat' => 'repeat',
                default => 'promote',
            }])
            ->all();
    }

    public function closeReview(): void
    {
        $this->classId = null;
        $this->decisions = [];
        $this->advice = [];
    }

    /**
     * Edit the promotion rules for the curriculum of the class under
     * review; the recommendations are worked out again straight away.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('rules')
                ->label('Promotion rules')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->modalHeading(fn () => 'Promotion rules — ' . (config('academics.curricula')[$this->rulesCurriculum()] ?? 'Primary'))
                ->modalDescription('How the system recommends each student. You always make the final decision per student.')
                ->modalSubmitActionLabel('Save rules')
                ->fillForm(fn () => PromotionRule::for(auth()->user()->school_id, $this->rulesCurriculum())->only([
                    'basis', 'min_average', 'probation_margin', 'required_subjects', 'subject_pass_mark', 'auto_promote_upto', 'min_points',
                ]))
                ->schema([
                    Select::make('basis')->label('Decide on')->options(PromotionRule::BASES)->required()->native(false),
                    TextInput::make('min_average')->label('Pass mark (average)')->numeric()->suffix('%')->minValue(0)->maxValue(100)->required()
                        ->helperText('Students at or above this average are promoted.'),
                    TextInput::make('probation_margin')->label('Probation band')->numeric()->suffix('%')->minValue(0)->maxValue(50)->required()
                        ->helperText('This far below the pass mark: "promoted on probation" instead of "advised to repeat".'),
                    TagsInput::make('required_subjects')->label('Subjects that must be passed')
                        ->placeholder('e.g. English, Mathematics')
                        ->helperText('Matched by name. Failing one gives probation (or repeat if the average is also low).'),
                    TextInput::make('subject_pass_mark')->label('Subject pass mark')->numeric()->suffix('%')->minValue(0)->maxValue(100)->required(),
                    TextInput::make('auto_promote_upto')->label('Promote automatically up to class number')->numeric()->minValue(1)->maxValue(9)
                        ->helperText('e.g. 3 = P1–P3 progress automatically, as in the thematic curriculum. Leave blank for none.')
                        ->visible(fn () => in_array($this->rulesCurriculum(), ['primary', 'nursery'], true)),
                    TextInput::make('min_points')->label('Minimum points (A-Level)')->numeric()->minValue(0)->maxValue(20)
                        ->helperText('e.g. 2 = at least one principal pass.')
                        ->visible(fn () => $this->rulesCurriculum() === 'a_level'),
                ])
                ->action(function (array $data) {
                    PromotionRule::for(auth()->user()->school_id, $this->rulesCurriculum())->update($data);
                    Notification::make()->title('Promotion rules saved')->success()->send();

                    if ($this->classId) {
                        $this->review($this->classId); // recommendations follow the new rules
                    }
                }),
        ];
    }

    /**
     * The curriculum whose rules the button edits: the class under review,
     * or the school's first curriculum.
     */
    public function rulesCurriculum(): string
    {
        return $this->reviewClass?->curriculum()
            ?? $this->service()->orderedClasses(auth()->user()->school_id)->map->curriculum()->filter()->first()
            ?? 'primary';
    }

    #[Computed]
    public function rule(): ?PromotionRule
    {
        return $this->reviewClass ? PromotionRule::for(auth()->user()->school_id, $this->reviewClass->curriculum()) : null;
    }

    public function setAll(string $action): void
    {
        if (array_key_exists($action, Promotion::ACTIONS)) {
            $this->decisions = array_map(fn () => $action, $this->decisions);
        }
    }

    #[Computed]
    public function reviewClass(): ?SchoolClass
    {
        return $this->classId ? SchoolClass::with('classLevel')->find($this->classId) : null;
    }

    #[Computed]
    public function targetClass(): ?SchoolClass
    {
        return $this->targetClassId ? SchoolClass::where('school_id', auth()->user()->school_id)->find($this->targetClassId) : null;
    }

    /**
     * Students under review, with this term's average and position to help
     * decide who repeats.
     *
     * @return Collection<int, array>
     */
    #[Computed]
    public function reviewRows(): Collection
    {
        $class = $this->reviewClass;

        if (! $class) {
            return collect();
        }

        $term = Term::current(auth()->user()->school_id);
        $results = $term ? app(ResultsCalculator::class)->forClass($class, $term)['rows']->keyBy(fn ($r) => $r['student']->id) : collect();
        $target = $this->targetClass;

        return $this->service()->pendingStudents($class, $this->year)
            ->filter(fn ($student) => $this->show === 'all'
                || in_array($this->advice[$student->id]['recommendation'] ?? 'promote', ['probation', 'repeat', 'no_results'], true))
            ->map(fn ($student) => [
            'student' => $student,
            'advice' => $this->advice[$student->id] ?? null,
            'average' => $results->get($student->id)['average'] ?? null,
            'position' => $results->get($student->id)['position'] ?? null,
            'to_section' => $target && $student->section
                ? (\App\Models\Section::find($this->service()->matchingSection($student->section, $target))?->name ?? 'no stream')
                : null,
        ]);
    }

    /** @return Collection<int, string> */
    public function targetOptions(): Collection
    {
        return $this->service()->orderedClasses(auth()->user()->school_id)
            ->reject(fn ($c) => $c->getKey() === $this->classId)
            ->pluck('name', 'id');
    }

    public function runPromotion(): void
    {
        $class = $this->reviewClass;

        if (! $class) {
            return;
        }

        if (in_array('promote', $this->decisions, true) && ! $this->targetClass) {
            Notification::make()->title('Choose the class to promote into')->danger()->send();

            return;
        }

        $counts = $this->service()->run($class, $this->decisions, $this->targetClass, $this->year, $this->advice);

        Notification::make()
            ->title("{$class->name} done")
            ->body(collect([
                $counts['promote'] ? "{$counts['promote']} promoted to {$this->targetClass?->name}" : null,
                $counts['probation'] ? "{$counts['probation']} promoted on probation" : null,
                $counts['repeat'] ? "{$counts['repeat']} repeating" : null,
                $counts['complete'] ? "{$counts['complete']} completed" : null,
                $counts['leave'] ? "{$counts['leave']} left" : null,
            ])->filter()->implode(', ') . '. You can undo this under History below.')
            ->success()
            ->send();

        $this->closeReview();
        unset($this->overview, $this->history);
    }

    /**
     * Recent runs, newest first.
     *
     * @return Collection<int, array>
     */
    #[Computed]
    public function history(): Collection
    {
        return Promotion::where('school_id', auth()->user()->school_id)
            ->with(['fromClass', 'toClass'])
            ->latest('id')
            ->limit(500)
            ->get()
            ->groupBy('batch')
            ->map(fn (Collection $rows) => [
                'batch' => $rows->first()->batch,
                'when' => $rows->first()->created_at,
                'by' => $rows->first()->performed_by,
                'class' => $rows->first()->fromClass?->name,
                'counts' => $rows->countBy('action')->all(),
                'reversed' => $rows->first()->reversed_at !== null,
            ])
            ->take(15)
            ->values();
    }

    public function undo(string $batch): void
    {
        $count = $this->service()->undo($batch, auth()->user()->school_id);

        Notification::make()->title("Undone: {$count} students put back")->success()->send();
        unset($this->overview, $this->history);
    }
}
