<?php

namespace App\Filament\Pages;

use App\Filament\Support\FeeReminderActions;
use App\Models\Student;
use App\Models\Term;
use App\Services\StudentLedger;
use App\Support\Modules;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

/**
 * One student's money, three ways.
 *
 *   Ledger     every charge and payment, running balance — the record
 *   Statement  one term, summarised — what a parent receives
 *   Invoice    what is owed right now — printed on demand, never stored
 *
 * All three read the same derived ledger, so they can never disagree with
 * each other. Reached from Student Accounts (not listed in the menu).
 */
class StudentAccount extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $title = 'Student Account';

    protected static ?string $slug = 'student-account';

    protected string $view = 'filament.pages.student-account';

    /** Which view is showing: ledger | statement | invoice */
    public string $mode = 'ledger';

    public ?int $studentId = null;

    public ?int $termId = null;

    /** @var array<string, mixed> */
    public ?array $picker = [];

    public function mount(): void
    {
        // ?student=123 links here from Student Accounts, Payments, etc.
        $this->studentId = request()->integer('student') ?: null;
        $this->termId = Term::current()?->getKey();

        $this->form->fill([
            'student_id' => $this->studentId,
            'term_id' => $this->termId,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('picker')
            ->components([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    Select::make('student_id')
                        ->label('Student')
                        ->placeholder('Search by name or admission number…')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => Student::query()
                            ->with('schoolClass')
                            ->where('school_id', auth()->user()?->school_id)
                            ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('admission_no', 'like', "%{$search}%"))
                            ->orderBy('name')
                            ->limit(25)
                            ->get()
                            ->mapWithKeys(fn (Student $s) => [$s->id => static::label($s)])
                            ->all())
                        ->getOptionLabelUsing(fn ($value) => static::label(Student::with('schoolClass')->find($value)))
                        ->live()
                        ->afterStateUpdated(fn ($state) => $this->studentId = $state ? (int) $state : null)
                        ->columnSpan(['default' => 1, 'md' => 2]),

                    Select::make('term_id')
                        ->label('Statement term')
                        ->options(fn () => Term::where('school_id', auth()->user()?->school_id)
                            ->with('academicYear')
                            ->get()
                            ->sortByDesc(fn (Term $t) => $t->sortKey())
                            ->mapWithKeys(fn (Term $t) => [$t->id => $t->label()])
                            ->all())
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(fn ($state) => $this->termId = $state ? (int) $state : null),
                ]),
            ]);
    }

    protected static function label(?Student $student): ?string
    {
        return $student
            ? trim(($student->name ?: 'No name')." — {$student->admission_no}".($student->schoolClass ? " ({$student->schoolClass->name})" : ''))
            : null;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getStudentProperty()?->name ?: 'Student Account';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('receive')
                ->label('Receive payment')
                ->icon('heroicon-o-banknotes')
                ->visible(fn () => $this->studentId !== null)
                ->url(fn () => ReceivePayment::getUrl(['student' => $this->studentId])),

            FeeReminderActions::smsFor(fn () => $this->getStudentProperty())
                ->visible(fn () => ($this->getStudentProperty()?->balance() ?? 0) > 0),

            Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->visible(fn () => $this->studentId !== null)
                ->action(fn () => $this->js('window.print()')),
        ];
    }

    // ── What the view needs ──

    public function getStudentProperty(): ?Student
    {
        return $this->studentId
            ? Student::with(['schoolClass', 'section', 'school', 'guardian', 'residencyType'])
                ->where('school_id', auth()->user()?->school_id)
                ->find($this->studentId)
            : null;
    }

    public function getTermProperty(): ?Term
    {
        return $this->termId ? Term::with('academicYear')->find($this->termId) : null;
    }

    public function getEntriesProperty(): Collection
    {
        $student = $this->getStudentProperty();

        if (! $student) {
            return collect();
        }

        $ledger = app(StudentLedger::class);

        // The ledger shows everything; the statement narrows to one term.
        return $this->mode === 'statement' && $this->getTermProperty()
            ? $ledger->entries($student, $this->getTermProperty())
            : $ledger->entries($student);
    }

    public function getSummaryProperty(): array
    {
        $student = $this->getStudentProperty();

        return $student
            ? app(StudentLedger::class)->summary($student)
            : ['charged' => 0, 'paid' => 0, 'balance' => 0, 'percent' => 0];
    }

    public function getTermSummaryProperty(): array
    {
        $student = $this->getStudentProperty();
        $term = $this->getTermProperty();

        return $student && $term
            ? app(StudentLedger::class)->termSummary($student, $term)
            : ['opening' => 0, 'charged' => 0, 'paid' => 0, 'closing' => 0];
    }

    public function setMode(string $mode): void
    {
        $this->mode = in_array($mode, ['ledger', 'statement', 'invoice'], true) ? $mode : 'ledger';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return Modules::allows('fees');
    }
}
