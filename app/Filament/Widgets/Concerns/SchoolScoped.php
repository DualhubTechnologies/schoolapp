<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\Term;

/**
 * Shared by the dashboard widgets: every figure is for the signed-in
 * user's school, and "this term" means the school's current term.
 */
trait SchoolScoped
{
    protected function schoolId(): ?int
    {
        return auth()->user()?->school_id;
    }

    protected function currentTerm(): ?Term
    {
        return once(fn () => Term::current($this->schoolId()));
    }

    /**
     * Money widgets are for the people who handle fees.
     */
    protected static function userHandlesFees(): bool
    {
        $user = auth()->user();

        return $user?->school_id !== null && \App\Support\Modules::allows('fees');
    }

    protected static function money(float $amount): string
    {
        return 'UGX ' . number_format($amount, 0);
    }

    /**
     * 1,250,000 -> "1.25M", 85,000 -> "85K": for stat cards, where a full
     * figure would not fit.
     */
    protected static function shortMoney(float $amount): string
    {
        return match (true) {
            abs($amount) >= 1_000_000_000 => 'UGX ' . round($amount / 1_000_000_000, 2) . 'B',
            abs($amount) >= 1_000_000 => 'UGX ' . round($amount / 1_000_000, 2) . 'M',
            abs($amount) >= 10_000 => 'UGX ' . round($amount / 1_000) . 'K',
            default => 'UGX ' . number_format($amount, 0),
        };
    }
}
