<?php

namespace App\Services;

use App\Filament\App\Resources\Billing\BillingResource;
use App\Filament\App\Resources\FeeReminders\FeeReminderResource;
use App\Filament\App\Resources\Payments\PaymentResource;
use App\Filament\Pages\ClassResults;
use App\Filament\Pages\Reports\FeeCollectionsReport;
use App\Filament\Pages\Reports\FeesByClassReport;
use App\Models\Mark;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\StudentPayment;
use App\Models\Term;
use App\Models\User;
use App\Support\Modules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * "Worth knowing today": plain rules over the school's own records that
 * point out what a head or bursar would want to notice (fees and marks;
 * attendance is left out on purpose), each with a link
 * to where it is dealt with. No AI service: every finding says what it is
 * based on, and only areas the user may open (Modules) are looked at.
 *
 *   - money in today
 *   - fee collection ahead of or behind last term at the same point
 *   - active learners with no fees billed this term, once it is billed
 *   - learners billed this term who have paid nothing
 *   - families who paid in each of the last two terms but not yet this one
 *   - possible double payments (same learner, amount and day)
 *   - learners whose average fell 10 points or more since last term
 *
 * @phpstan-type Finding array{key: string, tone: string, icon: string, title: string, detail: ?string, url: ?string}
 */
class SchoolInsights
{
    public const CACHE_SECONDS = 600;

    /** How far behind or ahead of last term (in points of the bill collected) is worth a mention. */
    public const PACE_GAP = 10;

    /** A fall in a learner's average (in percentage points) worth a mention. */
    public const MARKS_DROP = 10;

    /**
     * @return list<Finding>
     */
    public static function for(User $user, ?CarbonImmutable $today = null): array
    {
        if (! $user->school_id) {
            return [];
        }

        $today ??= now('Africa/Kampala')->toImmutable()->startOfDay();
        $key = 'insights:'.$user->getKey().':'.$today->toDateString();

        return Cache::remember($key, static::CACHE_SECONDS, fn () => (new self((int) $user->school_id, $today))->findings());
    }

    public function __construct(protected int $schoolId, protected CarbonImmutable $today) {}

    /** @return list<Finding> */
    public function findings(): array
    {
        $term = Term::current($this->schoolId);
        $items = [];

        if (Modules::allows('fees')) {
            $items = [
                ...$items,
                ...$this->doublePayments(),
                ...($term ? $this->collectionPace($term) : []),
                ...($term ? $this->notBilled($term) : []),
                ...($term ? $this->notPaidAnything($term) : []),
                ...($term ? $this->usualPayersNotYet($term) : []),
                ...$this->moneyToday(),
            ];
        }

        if (Modules::allows('exams') || Modules::allows('exams_all')) {
            $items = [...$items, ...$this->marksFalling()];
        }

        $rank = ['danger' => 0, 'warning' => 1, 'info' => 2, 'success' => 3];
        usort($items, fn (array $a, array $b): int => $rank[$a['tone']] <=> $rank[$b['tone']]);

        return $items;
    }

    // ── Fees ──

    /** @return list<Finding> */
    protected function moneyToday(): array
    {
        $payments = StudentPayment::where('school_id', $this->schoolId)->whereDate('paid_on', $this->today)->get(['amount']);

        if ($payments->isEmpty()) {
            return [];
        }

        return [$this->item('today', 'success', 'heroicon-o-banknotes',
            'UGX '.number_format((float) $payments->sum('amount')).' received today',
            $payments->count().' '.str('payment')->plural($payments->count()).' recorded so far.',
            FeeCollectionsReport::getUrl())];
    }

    /**
     * The share of the term's bill collected so far, against last term's
     * share by the same number of days into the term.
     *
     * @return list<Finding>
     */
    protected function collectionPace(Term $term): array
    {
        $previous = $term->previous();

        if (! $term->start_date || ! $previous?->start_date) {
            return [];
        }

        $daysIn = (int) $term->start_date->diffInDays($this->today);

        if ($daysIn < 7) {
            return [];
        }

        $now = $this->sharePaid($term, $term->start_date->toImmutable()->addDays($daysIn));
        $then = $this->sharePaid($previous, $previous->start_date->toImmutable()->addDays($daysIn));

        if ($now === null || $then === null || abs($now - $then) < static::PACE_GAP) {
            return [];
        }

        $week = intdiv($daysIn, 7) + 1;
        $behind = $now < $then;

        return [$this->item('pace', $behind ? 'warning' : 'success', $behind ? 'heroicon-o-arrow-trending-down' : 'heroicon-o-arrow-trending-up',
            $behind ? 'Fee collection is behind last term' : 'Fee collection is ahead of last term',
            "{$now}% of this term's fees collected by week {$week}, against {$then}% at the same point last term.",
            FeesByClassReport::getUrl())];
    }

    /** Whole-number percent of a term's bill paid by a date, or null when nothing was billed. */
    protected function sharePaid(Term $term, CarbonImmutable $by): ?int
    {
        $billed = (float) StudentCharge::where('term_id', $term->getKey())->selectRaw('sum(amount - coalesce(discount_amount, 0)) as total')->value('total');

        if ($billed <= 0) {
            return null;
        }

        $paid = (float) StudentPayment::where('term_id', $term->getKey())->whereDate('paid_on', '<=', $by)->sum('amount');

        return (int) round(min($paid, $billed) / $billed * 100);
    }

    /**
     * Active learners in a class with no fee on their account this term,
     * once the term has been billed (before that, the billing prompt on the
     * dashboard covers everyone): added since and missed, or in a class or
     * residency with no fees set.
     *
     * @return list<Finding>
     */
    protected function notBilled(Term $term): array
    {
        // Fees from the fee setup: a van fare or a one-off charge alone is not the term's bill.
        $billed = StudentCharge::where('term_id', $term->getKey())->whereNotNull('fee_structure_id')->distinct()->pluck('student_id');

        if ($billed->isEmpty()) {
            return [];
        }

        $ids = Student::where('school_id', $this->schoolId)
            ->where('status', 'active')
            ->whereNotNull('school_class_id')
            ->whereNotIn('id', $billed->all())
            ->pluck('id');

        if ($ids->isEmpty()) {
            return [];
        }

        return [$this->item('not-billed', 'danger', 'heroicon-o-document-minus',
            $ids->count().' '.($ids->count() === 1 ? 'learner is' : 'learners are').' in the system but not billed this term',
            $this->names($ids).'. "Bill a term" on Billing adds only what is missing; if nothing is added, check their class has fees set.',
            BillingResource::getUrl())];
    }

    /** @return list<Finding> */
    protected function notPaidAnything(Term $term): array
    {
        if (! $term->start_date || $term->start_date->diffInDays($this->today) < 21) {
            return [];
        }

        $ids = $this->billedIn($term)->diff($this->paidIn($term));

        if ($ids->isEmpty()) {
            return [];
        }

        return [$this->item('unpaid', 'warning', 'heroicon-o-exclamation-triangle',
            $ids->count().' '.($ids->count() === 1 ? 'learner has' : 'learners have').' paid nothing this term',
            $this->names($ids).'.',
            FeeReminderResource::getUrl())];
    }

    /** @return list<Finding> */
    protected function usualPayersNotYet(Term $term): array
    {
        $last = $term->previous();
        $before = $last?->previous();

        if (! $last || ! $before || ! $term->start_date || $term->start_date->diffInDays($this->today) < 14) {
            return [];
        }

        $ids = $this->paidIn($last)->intersect($this->paidIn($before))
            ->intersect($this->billedIn($term))
            ->diff($this->paidIn($term))
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return [$this->item('usual-payers', 'info', 'heroicon-o-bell-alert',
            $ids->count().' '.($ids->count() === 1 ? 'family that always pays has' : 'families that always pay have').' not paid yet this term',
            'They paid in each of the last two terms; a reminder usually does it. '.$this->names($ids).'.',
            FeeReminderResource::getUrl())];
    }

    /** @return list<Finding> */
    protected function doublePayments(): array
    {
        $pairs = StudentPayment::where('school_id', $this->schoolId)
            ->whereDate('paid_on', '>=', $this->today->subDays(30))
            ->selectRaw('student_id, amount, paid_on, count(*) as times')
            ->groupBy('student_id', 'amount', 'paid_on')
            ->havingRaw('count(*) > 1')
            ->get();

        if ($pairs->isEmpty()) {
            return [];
        }

        return [$this->item('doubles', 'danger', 'heroicon-o-document-duplicate',
            $pairs->count().' possible double '.str('payment')->plural($pairs->count()).' to check',
            'The same amount recorded twice for the same learner on the same day: '.$this->names($pairs->pluck('student_id')->unique()).'.',
            PaymentResource::getUrl())];
    }

    // ── Marks ──

    /**
     * Each learner's average (percent of each paper's maximum) in the two
     * latest terms that have marks; those 10 points or more down.
     *
     * @return list<Finding>
     */
    protected function marksFalling(): array
    {
        $averages = Mark::query()
            ->join('assessments', 'assessments.id', '=', 'marks.assessment_id')
            ->where('assessments.school_id', $this->schoolId)
            ->where('assessments.max_score', '>', 0)
            ->whereNotNull('marks.score')
            ->where('marks.is_absent', false)
            ->selectRaw('marks.student_id, assessments.term_id, avg(marks.score * 100.0 / assessments.max_score) as average')
            ->groupBy('marks.student_id', 'assessments.term_id')
            ->get();

        $terms = Term::whereIn('id', $averages->pluck('term_id')->unique())->with('academicYear')->get()
            ->sortByDesc(fn (Term $term): string => $term->sortKey())->values();

        if ($terms->count() < 2) {
            return [];
        }

        [$latest, $earlier] = [$terms[0], $terms[1]];
        $now = $averages->where('term_id', $latest->getKey())->pluck('average', 'student_id');
        $then = $averages->where('term_id', $earlier->getKey())->pluck('average', 'student_id');

        $ids = $now->filter(fn ($average, $id): bool => isset($then[$id]) && static::MARKS_DROP <= $then[$id] - $average)->keys();

        if ($ids->isEmpty()) {
            return [];
        }

        return [$this->item('marks', 'warning', 'heroicon-o-academic-cap',
            $ids->count().' '.str('learner')->plural($ids->count()).' whose marks fell '.static::MARKS_DROP.' points or more',
            'Average in '.$latest->label().' against '.$earlier->label().': '.$this->names($ids).'.',
            ClassResults::getUrl())];
    }

    // ── Helpers ──

    /** @return Collection<int, int> learners billed in a term */
    protected function billedIn(Term $term): Collection
    {
        return StudentCharge::where('term_id', $term->getKey())->distinct()->pluck('student_id')->map(fn ($id): int => (int) $id);
    }

    /** @return Collection<int, int> learners who paid towards a term */
    protected function paidIn(Term $term): Collection
    {
        return StudentPayment::where('term_id', $term->getKey())->distinct()->pluck('student_id')->map(fn ($id): int => (int) $id);
    }

    /**
     * "Aisha Nakato (S.2), Okello John (S.4) and 3 more", active learners only.
     *
     * @param  Collection<array-key, mixed>  $ids
     */
    protected function names(Collection $ids): string
    {
        $learners = Student::with('schoolClass')->where('school_id', $this->schoolId)->whereIn('id', $ids->all())->orderBy('name')->get();
        $shown = $learners->take(3)->map(fn (Student $s): string => $s->name.($s->schoolClass ? " ({$s->schoolClass->name})" : ''));
        $more = $learners->count() - $shown->count();

        return $shown->implode(', ').($more > 0 ? " and {$more} more" : '');
    }

    /** @return Finding */
    protected function item(string $key, string $tone, string $icon, string $title, ?string $detail, ?string $url): array
    {
        return compact('key', 'tone', 'icon', 'title', 'detail', 'url');
    }
}
