<?php

namespace App\Filament\Pages;

use App\Models\BudgetLine;
use App\Models\FinanceCategory;
use App\Models\Term;
use App\Services\Finance\FinanceReport;
use App\Support\FinanceAccess;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * The term budget: planned income and spending per category, with what
 * has actually come in and gone out so far beside it.
 */
class TermBudget extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Term Budget';

    protected static ?string $navigationLabel = 'Budget';

    protected string $view = 'filament.pages.term-budget';

    public ?int $termId = null;

    /** @var array<int, string|null> category id => amount as typed */
    public array $amounts = [];

    public static function canAccess(): bool
    {
        return FinanceAccess::allowed();
    }

    public function mount(): void
    {
        FinanceCategory::ensureDefaults(auth()->user()->school_id);
        $this->termId = Term::current(auth()->user()->school_id)?->getKey();
        $this->loadAmounts();
    }

    public function updatedTermId(): void
    {
        $this->loadAmounts();
    }

    /** @return Collection<int, string> */
    public function termOptions(): Collection
    {
        return Term::where('school_id', auth()->user()->school_id)
            ->with('academicYear')
            ->get()
            ->sortByDesc(fn (Term $t) => $t->sortKey())
            ->mapWithKeys(fn (Term $t) => [$t->id => $t->label()]);
    }

    #[Computed]
    public function term(): ?Term
    {
        return $this->termId ? Term::where('school_id', auth()->user()->school_id)->find($this->termId) : null;
    }

    #[Computed]
    public function categories(): Collection
    {
        return FinanceCategory::where('school_id', auth()->user()->school_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->groupBy('type');
    }

    /**
     * What actually came in / went out this term, per category id.
     *
     * @return array<int, float>
     */
    #[Computed]
    public function actuals(): array
    {
        $term = $this->term;

        if (! $term?->start_date || ! $term?->end_date) {
            return [];
        }

        $report = app(FinanceReport::class)->build(auth()->user()->school_id, $term->start_date->copy(), $term->end_date->copy(), collect([$term]));

        return $report['income']->concat($report['expense'])->mapWithKeys(fn ($r) => [$r['category']->id => $r['actual']])->all();
    }

    protected function loadAmounts(): void
    {
        $lines = $this->termId ? BudgetLine::where('term_id', $this->termId)->pluck('amount', 'finance_category_id') : collect();

        $this->amounts = [];
        foreach ($this->categories->flatten() as $category) {
            $this->amounts[$category->id] = isset($lines[$category->id]) ? (string) (int) $lines[$category->id] : null;
        }

        unset($this->actuals);
    }

    public function copyPrevious(): void
    {
        $previous = $this->term?->previous();

        if (! $previous) {
            Notification::make()->title('No earlier term to copy from')->warning()->send();

            return;
        }

        $lines = BudgetLine::where('term_id', $previous->getKey())->pluck('amount', 'finance_category_id');

        if ($lines->isEmpty()) {
            Notification::make()->title("{$previous->label()} has no budget")->warning()->send();

            return;
        }

        foreach ($lines as $categoryId => $amount) {
            if (array_key_exists($categoryId, $this->amounts)) {
                $this->amounts[$categoryId] = (string) (int) $amount;
            }
        }

        Notification::make()->title("Copied from {$previous->label()}")->body('Adjust anything that changes, then save.')->info()->send();
    }

    public function save(): void
    {
        if (! $this->term) {
            return;
        }

        foreach ($this->amounts as $categoryId => $raw) {
            $amount = is_numeric(str_replace(',', '', (string) $raw)) ? (float) str_replace(',', '', (string) $raw) : 0;

            if ($amount <= 0) {
                BudgetLine::where('term_id', $this->termId)->where('finance_category_id', $categoryId)->delete();

                continue;
            }

            BudgetLine::updateOrCreate(
                ['term_id' => $this->termId, 'finance_category_id' => $categoryId],
                ['school_id' => auth()->user()->school_id, 'amount' => $amount],
            );
        }

        Notification::make()->title('Budget saved')->success()->send();
    }
}
