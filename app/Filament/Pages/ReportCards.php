<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ChoosesExam;
use App\Models\Mark;
use App\Models\ReportCardTemplate;
use App\Models\ResidencyType;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Term;
use App\Models\TermReport;
use App\Services\Academics\MarksCompleteness;
use App\Services\Academics\ResultsCalculator;
use App\Services\ParentMessages;
use App\Support\AcademicAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;

/**
 * Write the term's report-card comments for a class, then print the
 * cards -- the whole class at once, the learners a search or filter
 * shows, or one student.
 *
 * @property-read array<string, mixed>|null $results
 */
class ReportCards extends Page
{
    use ChoosesExam;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Exams & Results';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Report Cards';

    protected string $view = 'filament.pages.report-cards';

    public ?int $termId = null;

    public ?int $classId = null;

    public ?int $sectionId = null;

    public bool $showFees = true;

    /** What is typed in the search box; applied by the Search button or Enter. */
    public string $searchInput = '';

    /** The search in force: name, admission number or LIN. */
    public string $search = '';

    /** 'male', 'female' or '' for all. */
    public string $gender = '';

    public ?int $residencyId = null;

    /** '' all, 'missing' no class teacher's comment yet, 'written' has one. */
    public string $commentFilter = '';

    public const COMMENT_FILTERS = [
        '' => 'All learners',
        'missing' => 'No comment yet',
        'written' => 'Comment written',
    ];

    /** @var array<int, array{class_teacher_comment: ?string, conduct: ?string}> */
    public array $comments = [];

    /** One head teacher's comment, printed on every report card of the class. */
    public ?string $headComment = null;

    public const CONDUCT = ['Excellent', 'Very good', 'Good', 'Fair', 'Needs improvement'];

    /**
     * Share the chosen term's report cards with parents on their parent
     * page, and text them the link. Heads and the director of studies only.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->templateAction(),

            Action::make('shareWithParents')
                ->label('Share with parents')
                ->icon('heroicon-o-paper-airplane')
                ->visible(fn (): bool => AcademicAccess::manages() && ! $this->selectedTerm()?->report_cards_released_at)
                ->modalHeading(fn (): string => 'Share '.$this->selectedTerm()?->label().' report cards with parents')
                ->modalDescription(fn (): HtmlString => $this->shareDescription())
                ->schema([
                    Toggle::make('text_parents')
                        ->label('Text every family the link now')
                        ->helperText('One SMS per learner with marks this term.')
                        ->default(true),
                    Checkbox::make('share_anyway')
                        ->label('Some exams are not entered yet. Share anyway.')
                        ->visible(fn (): bool => $this->missingMarks()->isNotEmpty())
                        ->accepted()
                        ->validationMessages(['accepted' => 'Enter the missing marks first, or tick this to share anyway.']),
                ])
                ->modalSubmitActionLabel('Share report cards')
                ->action(function (array $data): void {
                    $term = $this->selectedTerm();

                    if (! $term) {
                        return;
                    }

                    $term->forceFill(['report_cards_released_at' => now()])->save();
                    $body = 'Parents can now open them from their SchoolHub link.';

                    if ($data['text_parents'] ?? false) {
                        $students = Student::where('school_id', $term->school_id)
                            ->where('status', 'active')
                            ->whereIn('id', Mark::join('assessments', 'assessments.id', '=', 'marks.assessment_id')
                                ->where('assessments.term_id', $term->getKey())
                                ->select('marks.student_id'))
                            ->get();

                        $sent = app(ParentMessages::class)->sendReportCardsReady($students, $term);
                        $body .= " {$sent['sent']} texted".($sent['no_phone'] ? ", {$sent['no_phone']} without a phone number" : '').($sent['failed'] ? ", {$sent['failed']} failed" : '').'.';
                    }

                    Notification::make()->title('Report cards shared with parents')->body($body)->success()->send();
                }),

            Action::make('stopSharing')
                ->label('Stop sharing with parents')
                ->icon('heroicon-o-eye-slash')
                ->color('gray')
                ->visible(fn (): bool => AcademicAccess::manages() && (bool) $this->selectedTerm()?->report_cards_released_at)
                ->requiresConfirmation()
                ->modalDescription('Parents will no longer see this term\'s report cards on their page. You can share them again at any time.')
                ->action(function (): void {
                    $this->selectedTerm()?->forceFill(['report_cards_released_at' => null])->save();
                    Notification::make()->title('Report cards hidden from parents')->success()->send();
                }),
        ];
    }

    /**
     * The school's report card look: design, colours, header and footer,
     * and which parts are printed. Saved for the school, so it is set once;
     * until then the defaults are used.
     */
    protected function templateAction(): Action
    {
        return Action::make('template')
            ->label('Template')
            ->icon('heroicon-o-swatch')
            ->color('gray')
            ->visible(fn (): bool => AcademicAccess::manages())
            ->modalHeading('Report card template')
            ->modalDescription('How your school\'s report cards look. Saved for the school: every printed card, and every card parents open, uses it until you change it. Print one learner\'s card to see it.')
            ->modalSubmitActionLabel('Save template')
            ->modalWidth('3xl')
            ->fillForm(function (): array {
                $template = $this->cardTemplate();

                return [
                    ...ReportCardTemplate::DEFAULTS,
                    ...$template->only(['design', 'font', 'border', 'primary_color', 'accent_color', 'title', 'header_note', 'footer_text', 'watermark']),
                    'show' => $template->shownSections(),
                ];
            })
            ->schema([
                FormSection::make('Design')
                    ->schema([
                        ToggleButtons::make('design')
                            ->hiddenLabel()
                            ->options(ReportCardTemplate::DESIGNS)
                            ->icons(['classic' => 'heroicon-o-document-text', 'modern' => 'heroicon-o-rectangle-stack', 'compact' => 'heroicon-o-bars-3'])
                            ->helperText(fn ($state): string => ReportCardTemplate::DESIGN_HINTS[$state] ?? '')
                            ->live()
                            ->inline()
                            ->required(),
                        Grid::make(2)->schema([
                            ColorPicker::make('primary_color')
                                ->label('Main colour')
                                ->helperText('School name, headings, grades.')
                                ->regex('/^#[0-9a-fA-F]{6}$/')
                                ->required(),
                            ColorPicker::make('accent_color')
                                ->label('Accent colour')
                                ->helperText('Trim lines and the ornate border.')
                                ->regex('/^#[0-9a-fA-F]{6}$/')
                                ->required(),
                            Select::make('border')
                                ->label('Page border')
                                ->options(ReportCardTemplate::BORDERS)
                                ->native(false)
                                ->required(),
                            Select::make('font')
                                ->label('Lettering')
                                ->options(ReportCardTemplate::FONTS)
                                ->native(false)
                                ->required(),
                        ]),
                    ]),
                FormSection::make('Header and footer')
                    ->description('The school name, logo, address, phone, email and motto come from the school profile.')
                    ->schema([
                        TextInput::make('title')
                            ->label('Report title')
                            ->placeholder('e.g. End of Term Report — leave empty for the usual title')
                            ->maxLength(80),
                        TextInput::make('header_note')
                            ->label('Extra line under the school name')
                            ->placeholder('e.g. P.O. Box 123, Mbarara · Reg. No. ME/P/1234')
                            ->maxLength(160),
                        Textarea::make('footer_text')
                            ->label('Footer')
                            ->placeholder('e.g. This report is not valid without the school stamp.')
                            ->rows(2)
                            ->maxLength(300),
                        Toggle::make('watermark')
                            ->label('Faint school logo behind the page')
                            ->helperText('Needs a logo in the school profile.'),
                    ]),
                FormSection::make('What to print')
                    ->description('Untick anything your school does not put on its report cards.')
                    ->schema([
                        CheckboxList::make('show')
                            ->hiddenLabel()
                            ->options(collect(ReportCardTemplate::SECTIONS)->map(fn (array $s): string => $s[0])->all())
                            ->columns(2)
                            ->bulkToggleable(),
                    ]),
            ])
            ->extraModalFooterActions(fn (Action $action): array => [
                Action::make('resetTemplate')
                    ->label('Back to default')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Report cards go back to the standard SchoolHub design, with every part printed.')
                    ->action(function () use ($action): void {
                        ReportCardTemplate::where('school_id', auth()->user()?->school_id)->delete();
                        $this->showFees = true;
                        Notification::make()->title('Template reset to the default')->success()->send();
                        $action->cancel();
                    }),
            ])
            ->action(function (array $data): void {
                $schoolId = auth()->user()?->school_id;

                if (! $schoolId || ! AcademicAccess::manages()) {
                    return;
                }

                $show = ReportCardTemplate::showFromTicked($data['show'] ?? []);

                ReportCardTemplate::updateOrCreate(['school_id' => $schoolId], [
                    'design' => $data['design'],
                    'font' => $data['font'],
                    'border' => $data['border'],
                    'primary_color' => strtolower($data['primary_color']),
                    'accent_color' => strtolower($data['accent_color']),
                    'title' => trim((string) ($data['title'] ?? '')) ?: null,
                    'header_note' => trim((string) ($data['header_note'] ?? '')) ?: null,
                    'footer_text' => trim((string) ($data['footer_text'] ?? '')) ?: null,
                    'watermark' => (bool) ($data['watermark'] ?? false),
                    'show' => $show,
                ]);

                $this->showFees = $show['fees'];

                Notification::make()->title('Template saved')->body('Your school\'s report cards will use it from now on.')->success()->send();
            });
    }

    public function cardTemplate(): ReportCardTemplate
    {
        return ReportCardTemplate::forSchool((int) auth()->user()?->school_id);
    }

    /**
     * Exams with no marks yet in the chosen term, class by class.
     *
     * @return Collection<int, array{class: string, missing: list<string>}>
     */
    public function missingMarks(): Collection
    {
        $term = $this->selectedTerm();

        return $term ? collect(once(fn (): array => app(MarksCompleteness::class)->missingFor($term))) : collect();
    }

    protected function shareDescription(): HtmlString
    {
        $text = e('Each family can then open their own child\'s report card from their private SchoolHub link, on any phone. Finish all marks and comments first.');
        $missing = $this->missingMarks();

        if ($missing->isEmpty()) {
            return new HtmlString($text.'<br><br><strong>Every exam has marks entered.</strong>');
        }

        $list = $missing->map(fn (array $c): string => '<li><strong>'.e($c['class']).':</strong> '.e(implode(', ', array_slice($c['missing'], 0, 6)))
            .(count($c['missing']) > 6 ? e(' and '.(count($c['missing']) - 6).' more') : '').'</li>')->implode('');

        return new HtmlString($text.'<br><br><strong style="color:#b45309">Not entered yet:</strong><ul style="margin:.3rem 0 0 1.1rem;list-style:disc">'.$list.'</ul>');
    }

    public function selectedTerm(): ?Term
    {
        return $this->termId ? Term::where('school_id', auth()->user()?->school_id)->with('academicYear')->find($this->termId) : null;
    }

    public function mount(): void
    {
        $this->showFees = $this->cardTemplate()->shows('fees');
        $this->termId = request()->integer('term') ?: Term::current()?->getKey();
        $this->classId = request()->integer('class') ?: null;
        $this->pickOwnStream();
        $this->loadComments();
    }

    public static function canAccess(): bool
    {
        return AcademicAccess::teaches();
    }

    public function updatedTermId(): void
    {
        $this->examId = null;
        $this->loadComments();
    }

    public function updatedExamId(): void
    {
        $this->loadComments();
    }

    public function updatedClassId(): void
    {
        $this->sectionId = null;
        $this->pickOwnStream();
        $this->loadComments();
    }

    /** Teachers only work on the stream they are class teacher of. */
    protected function pickOwnStream(): void
    {
        if (! AcademicAccess::manages()) {
            $this->sectionId = AcademicAccess::classTeacherStreamsIn($this->classId)[0] ?? null;
        }
    }

    public function updatedSectionId(): void
    {
        $this->loadComments();
    }

    /** The Search button (or Enter in the search box). */
    public function applySearch(): void
    {
        $this->search = trim($this->searchInput);
    }

    public function clearSearch(): void
    {
        $this->searchInput = '';
        $this->search = '';
    }

    /** Back to the whole class or stream. */
    public function clearFilters(): void
    {
        $this->gender = '';
        $this->residencyId = null;
        $this->commentFilter = '';
        $this->clearSearch();
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->gender !== '' || $this->residencyId !== null || $this->commentFilter !== '';
    }

    /** @return Collection<int, string> */
    public function residencyOptions(): Collection
    {
        return ResidencyType::where('school_id', auth()->user()?->school_id)->orderBy('name')->pluck('name', 'id');
    }

    /**
     * The class's rows narrowed by the search and filters. Comments are
     * still saved for everyone: hidden rows keep what was typed.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function visibleRows(): Collection
    {
        $needle = mb_strtolower($this->search);

        return ($this->results ? $this->results['rows'] : collect())
            ->filter(function (array $row) use ($needle): bool {
                /** @var Student $student */
                $student = $row['student'];

                if ($needle !== '' && ! str_contains(mb_strtolower(implode(' ', [$student->name, $student->admission_no, $student->lin])), $needle)) {
                    return false;
                }

                if ($this->gender !== '' && $student->gender !== $this->gender) {
                    return false;
                }

                if ($this->residencyId && (int) $student->residency_type_id !== $this->residencyId) {
                    return false;
                }

                $hasComment = filled($this->comments[$student->id]['class_teacher_comment'] ?? null);

                return match ($this->commentFilter) {
                    'missing' => ! $hasComment,
                    'written' => $hasComment,
                    default => true,
                };
            })
            ->values();
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
        return SchoolClass::where('school_id', auth()->user()?->school_id)
            ->when(! AcademicAccess::manages(), fn ($q) => $q->whereIn('id', array_values(AcademicAccess::classTeacherStreams())))
            ->orderBy('level')
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    /** @return Collection<int, string> */
    public function sectionOptions(): Collection
    {
        return $this->classId
            ? Section::where('school_class_id', $this->classId)
                ->when(! AcademicAccess::manages(), fn ($q) => $q->whereIn('id', AcademicAccess::classTeacherStreamsIn($this->classId)))
                ->orderBy('name')
                ->pluck('name', 'id')
            : collect();
    }

    #[Computed]
    public function results(): ?array
    {
        $class = $this->classId ? SchoolClass::where('school_id', auth()->user()?->school_id)->find($this->classId) : null;
        $term = $this->termId ? Term::where('school_id', auth()->user()?->school_id)->find($this->termId) : null;

        // A teacher sees only the stream they are class teacher of.
        if (! AcademicAccess::manages() && ! in_array($this->sectionId, AcademicAccess::classTeacherStreamsIn($this->classId), true)) {
            return null;
        }

        return $class && $term ? app(ResultsCalculator::class)->forClass($class, $term, $this->sectionId, $this->chosenExamId()) : null;
    }

    protected function loadComments(): void
    {
        unset($this->results);
        $this->comments = [];
        $this->headComment = collect($this->results['rows'] ?? [])
            ->pluck('report.head_teacher_comment')
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        foreach ($this->results['rows'] ?? [] as $row) {
            $report = $row['report'];
            $this->comments[$row['student']->id] = [
                'class_teacher_comment' => $report?->class_teacher_comment,
                'conduct' => $report?->conduct,
            ];
        }
    }

    /**
     * Suggest a class-teacher comment for everyone who has none, from
     * their average, and save them.
     */
    public function fillComments(): void
    {
        $bands = collect(config('academics.comments'))->sortKeysDesc();
        $filled = 0;

        foreach ($this->results['rows'] ?? [] as $row) {
            $id = $row['student']->id;

            if ($row['average'] === null || filled($this->comments[$id]['class_teacher_comment'] ?? null)) {
                continue;
            }

            $this->comments[$id]['class_teacher_comment'] = $bands->first(fn ($text, $min) => $row['average'] >= $min);
            $this->updatedComments($this->comments[$id]['class_teacher_comment'], "{$id}.class_teacher_comment");
            $filled++;
        }

        Notification::make()->title("{$filled} comments filled in and saved")->body('Change any of them: each comment saves as soon as you finish typing it.')->success()->send();
    }

    /**
     * A class teacher's comment or conduct is saved as soon as it is
     * typed or chosen, so it is on the report card even if Save comments
     * is never pressed. $key is "<student id>.<field>".
     */
    public function updatedComments(mixed $value, string $key): void
    {
        [$id, $field] = array_pad(explode('.', $key, 2), 2, null);
        $id = (int) $id;

        if (! in_array($field, ['class_teacher_comment', 'conduct'], true)
            || ! collect($this->results['rows'] ?? [])->contains(fn ($row) => $row['student']->id === $id)) {
            return;
        }

        TermReport::updateOrCreate(
            ['student_id' => $id, 'term_id' => $this->termId],
            [$field => $field === 'class_teacher_comment' ? (trim((string) $value) ?: null) : ($value ?: null)],
        );
    }

    /** The head teacher's comment is saved for the whole class as soon as it is typed. */
    public function updatedHeadComment(): void
    {
        if (! AcademicAccess::manages()) {
            return;
        }

        $head = trim((string) $this->headComment) ?: null;

        foreach (collect($this->results['rows'] ?? [])->pluck('student.id') as $id) {
            TermReport::updateOrCreate(['student_id' => $id, 'term_id' => $this->termId], ['head_teacher_comment' => $head]);
        }
    }

    public function saveComments(): void
    {
        $studentIds = collect($this->results['rows'] ?? [])->pluck('student.id');
        $isHead = AcademicAccess::manages();
        $head = trim((string) $this->headComment) ?: null;

        foreach ($studentIds as $id) {
            $data = $this->comments[$id] ?? [];
            $values = [
                'class_teacher_comment' => trim((string) ($data['class_teacher_comment'] ?? '')) ?: null,
                'conduct' => $data['conduct'] ?? null,
            ];

            // Only the head teacher / administrator writes the head's comment.
            if ($isHead) {
                $values['head_teacher_comment'] = $head;
            }

            TermReport::updateOrCreate(['student_id' => $id, 'term_id' => $this->termId], $values);
        }

        unset($this->results);
        Notification::make()->title('Comments saved')->success()->send();
    }

    public function printUrl(?int $studentId = null): string
    {
        // "Print all" after a search or filter prints just the learners shown.
        $shown = ! $studentId && $this->hasFilters()
            ? $this->visibleRows()->pluck('student.id')->implode(',')
            : null;

        return route('filament.app.academics.report-cards', array_filter([
            'term' => $this->termId,
            'class' => $this->classId,
            'section' => $this->sectionId,
            'student' => $studentId,
            'exam' => $this->chosenExamId(),
            'students' => $shown ?: null,
            'fees' => $this->showFees ? 1 : 0,
            'print' => 1,
        ], fn ($v) => $v !== null));
    }
}
