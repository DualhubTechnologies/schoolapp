<?php

namespace App\Filament\Pages;

use App\Models\Student;
use App\Models\Term;
use App\Services\StudentLedger;
use BackedEnum;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * One student's money, three ways.
 *
 *   Ledger     every charge and payment, running balance — the record
 *   Statement  one term, summarised — what a parent receives
 *   Invoice    what is owed right now — printed on demand, never stored
 *
 * All three read the same derived ledger, so they can never disagree
 * with each other.
 */
class StudentAccount extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'Fees';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Student Account';

    protected static ?string $navigationLabel = 'Student Account';

    protected string $view = 'filament.pages.student-account';

    /** Which view is showing: ledger | statement | invoice */
    public string $mode = 'ledger';

    public ?int $studentId = null;

    public ?int $termId = null;

    public function mount(): void
    {
        // Allows linking straight here from the Bill Students or Fee
        // Balances tables: ?student=123
        $this->studentId = request()->integer('student') ?: null;
        $this->termId = Term::current()?->getKey();
    }

    // ── What the view needs ──

    public function getStudentProperty(): ?Student
    {
        return $this->studentId
            ? Student::with(['schoolClass', 'section', 'school'])->find($this->studentId)
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

    /**
     * Students the signed-in user may look at, for the picker.
     */
    public function getStudentOptionsProperty(): array
    {
        return Student::query()
            ->when(
                ! auth()->user()?->hasRole('Super Admin'),
                fn (Builder $q) => $q->where('school_id', auth()->user()?->school_id),
            )
            ->orderBy('name')
            ->limit(500)
            ->get()
            ->mapWithKeys(fn (Student $s) => [$s->id => "{$s->name} ({$s->admission_no})"])
            ->toArray();
    }

    public function getTermOptionsProperty(): array
    {
        return Term::query()
            ->where('school_id', auth()->user()?->school_id)
            ->with('academicYear')
            ->get()
            ->mapWithKeys(fn (Term $t) => [$t->id => $t->label()])
            ->toArray();
    }

    public function setMode(string $mode): void
    {
        $this->mode = in_array($mode, ['ledger', 'statement', 'invoice'], true) ? $mode : 'ledger';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole(['Super Admin', 'School Admin', 'Accountant', 'Bursar']) ?? false;
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }
}
