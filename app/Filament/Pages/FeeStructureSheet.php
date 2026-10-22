<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\PlacedInMenu;
use App\Models\Term;
use App\Services\FeeSheet;
use App\Support\Modules;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * The fees structure as a school publishes it (worked out by FeeSheet):
 * fees per term for each group of classes, what a new learner pays on
 * admission, the first term in all, other charges, the term's dates, how
 * to pay, and the school's requirements and notes. Prints on A4.
 */
class FeeStructureSheet extends Page
{
    use PlacedInMenu;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;

    protected static ?string $title = 'Fees Structure';

    protected string $view = 'filament.pages.fee-structure-sheet';

    public ?int $termId = null;

    public function mount(): void
    {
        $this->termId = Term::current()?->getKey();
    }

    public function getTermProperty(): ?Term
    {
        return $this->termId ? Term::with('academicYear')->find($this->termId) : null;
    }

    /** @return array<int, string> term id => "Term 1 - 2026" */
    public function getTermOptionsProperty(): array
    {
        return Term::query()
            ->where('school_id', auth()->user()?->school_id)
            ->with('academicYear')
            ->get()
            ->mapWithKeys(fn (Term $t) => [$t->id => $t->label()])
            ->toArray();
    }

    /**
     * The sheet for the chosen term: fees per term, admission fees, first
     * term totals and other charges (see FeeSheet).
     */
    public function getSheetProperty(): ?FeeSheet
    {
        $term = $this->getTermProperty();

        return $term ? new FeeSheet($term) : null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Modules::allows('fees');
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }
}
