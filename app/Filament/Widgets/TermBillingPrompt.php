<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\StartTermBilling;
use App\Filament\Widgets\Concerns\SchoolScoped;
use App\Models\Term;
use App\Services\BillingService;
use Filament\Widgets\Widget;

/**
 * "Term 2 has started and nobody has been billed": shown to whoever
 * handles fees until the current term is billed, with the way in to
 * keep or update the fees and bill everyone (StartTermBilling).
 */
class TermBillingPrompt extends Widget
{
    use SchoolScoped;

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.term-billing-prompt';

    public static function canView(): bool
    {
        if (filament()->getCurrentPanel()?->getId() !== 'app' || ! StartTermBilling::canAccess()) {
            return false;
        }

        $term = Term::current(auth()->user()?->school_id);

        return $term !== null && app(BillingService::class)->termNeedsBilling($term);
    }

    public function term(): ?Term
    {
        return $this->currentTerm();
    }

    public function url(): string
    {
        return StartTermBilling::getUrl();
    }
}
