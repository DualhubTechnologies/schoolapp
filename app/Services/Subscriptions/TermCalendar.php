<?php

namespace App\Services\Subscriptions;

use App\Models\SchoolCalendarTerm;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Paid SchoolHub terms follow Uganda's school calendar, so a school pays
 * at the start of a term, when fees come in:
 *
 * - A term covers the school term going on (or the next one, when paid in
 *   the holidays) up to the day before the following term opens, so the
 *   holidays are included.
 * - Paid in the last weeks of a term, it covers the next term instead:
 *   nobody pays a full term for a few days.
 * - A year covers three terms the same way.
 *
 * Null when the calendar does not reach far enough; the caller then falls
 * back to counting months.
 */
class TermCalendar
{
    /** Paid with fewer days than this left in a term: the next term is covered. */
    public const LATE_IN_TERM_DAYS = 28;

    public static function periodEnd(CarbonImmutable $start, string $cycle): ?CarbonImmutable
    {
        $steps = match ($cycle) {
            'term' => 1,
            'year' => 3,
            default => null,
        };

        if ($steps === null) {
            return null;
        }

        $terms = static::terms();
        $index = static::coveredIndex($terms, $start);
        $next = $index === null ? null : $terms->get($index + $steps);

        return $next ? CarbonImmutable::parse($next->opens_on)->subDay() : null;
    }

    /** The term a payment starting on $start is for. */
    public static function coveredTerm(CarbonImmutable $start): ?SchoolCalendarTerm
    {
        $terms = static::terms();
        $index = static::coveredIndex($terms, $start);

        return $index === null ? null : $terms->get($index);
    }

    /**
     * @param  Collection<int, SchoolCalendarTerm>  $terms
     */
    protected static function coveredIndex(Collection $terms, CarbonImmutable $start): ?int
    {
        foreach ($terms as $i => $term) {
            $opens = CarbonImmutable::parse($term->opens_on);
            $closes = CarbonImmutable::parse($term->closes_on);

            // In the holidays before this term: this term.
            if ($start->lt($opens)) {
                return $i;
            }

            // During this term: this term, or the next if it is nearly over.
            if ($start->lte($closes)) {
                return $start->diffInDays($closes) < self::LATE_IN_TERM_DAYS && $terms->has($i + 1) ? $i + 1 : $i;
            }
        }

        return null;
    }

    /** @return Collection<int, SchoolCalendarTerm> */
    protected static function terms(): Collection
    {
        return SchoolCalendarTerm::orderBy('opens_on')->get()->values();
    }
}
