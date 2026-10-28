<?php

namespace App\Services;

use App\Filament\App\Resources\Billing\BillingResource;
use App\Filament\App\Resources\FeeReminders\FeeReminderResource;
use App\Filament\App\Resources\LibraryLoans\LibraryLoanResource;
use App\Filament\App\Resources\Payments\PaymentResource;
use App\Filament\App\Resources\SickBayVisits\SickBayVisitResource;
use App\Filament\App\Resources\StoreItems\StoreItemResource;
use App\Filament\App\Resources\Students\StudentResource;
use App\Filament\Pages\ClassResults;
use App\Filament\Pages\Reports\FeeCollectionsReport;
use App\Filament\Pages\Reports\FeesByClassReport;
use App\Filament\Pages\StartTermBilling;
use App\Models\LibraryLoan;
use App\Models\Mark;
use App\Models\SickBayVisit;
use App\Models\StoreItem;
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
 *   - active learners with no fees billed this term (the whole term, if unbilled)
 *   - learners with no parent or phone number on record
 *   - learners billed this term who have paid nothing
 *   - families who paid in each of the last two terms but not yet this one
 *   - possible double payments (same learner, amount and day)
 *   - library books not returned (overdue, or near the end of term)
 *   - learners resting in the sick bay
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

        if (Modules::allows('students') || Modules::allows('fees')) {
            $items = [...$items, ...$this->noParentContact()];
        }

        if (Modules::allows('library')) {
            $items = [...$items, ...$this->booksNotReturned($term)];
        }

        if (Modules::allows('sickbay')) {
            $items = [...$items, ...$this->restingInSickBay()];
        }

        if (Modules::allows('stores')) {
            $items = [...$items, ...$this->storesRunningLow()];
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
     * Active learners in a class with no fee on their account this term
     * (a van fare or one-off charge alone is not the term's bill). Before
     * anyone is billed it is the whole term to start; after, the learners
     * added since and missed, or in a class with no fees set.
     *
     * @return list<Finding>
     */
    protected function notBilled(Term $term): array
    {
        $billed = StudentCharge::where('term_id', $term->getKey())->whereNotNull('fee_structure_id')->distinct()->pluck('student_id');

        $ids = Student::where('school_id', $this->schoolId)
            ->where('status', 'active')
            ->whereNotNull('school_class_id')
            ->whereNotIn('id', $billed->all())
            ->pluck('id');

        if ($ids->isEmpty()) {
            return [];
        }

        if ($billed->isEmpty()) {
            return [$this->item('not-billed', 'danger', 'heroicon-o-document-minus',
                $term->label().' has not been billed yet',
                number_format($ids->count()).' '.($ids->count() === 1 ? 'learner has' : 'learners have').' no fees for this term, so no balances, invoices or reminders. Start the term to bill everyone in one go.',
                StartTermBilling::getUrl())];
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

    // ── Learners ──

    /**
     * Active learners with no parent on record or no phone number to reach
     * the family: reminders, SMS and parent logins cannot reach them.
     *
     * @return list<Finding>
     */
    protected function noParentContact(): array
    {
        $ids = Student::where('school_id', $this->schoolId)
            ->where('status', 'active')
            ->withoutParentContact()
            ->pluck('id');

        if ($ids->isEmpty()) {
            return [];
        }

        return [$this->item('no-contact', 'warning', 'heroicon-o-phone-x-mark',
            $ids->count().' '.($ids->count() === 1 ? 'learner has' : 'learners have').' no parent or phone number',
            'Fee reminders, messages and parent logins cannot reach them: '.$this->names($ids).'.'
                .(StudentResource::canAccess() ? '' : ' The admissions office can add their parents.'),
            StudentResource::canAccess()
                ? StudentResource::getUrl('index', ['filters' => ['status' => ['value' => 'active'], 'parent_contact' => ['value' => '0']]])
                : null)];
    }

    // ── Library and sick bay ──

    /**
     * Books still out that are overdue, or, in the last two weeks of the
     * term (and after it ends), every book a learner still has.
     *
     * @return list<Finding>
     */
    protected function booksNotReturned(?Term $term): array
    {
        $endingSoon = $term?->end_date && $this->today->gte($term->end_date->toImmutable()->subDays(14));

        $loans = LibraryLoan::outstanding()
            ->where('school_id', $this->schoolId)
            ->whereNotNull('student_id')
            ->when(! $endingSoon, fn ($q) => $q->whereDate('due_on', '<', $this->today))
            ->get(['student_id']);

        if ($loans->isEmpty()) {
            return [];
        }

        $title = $loans->count().' library '.str('book')->plural($loans->count()).' not returned';

        return [$this->item('library', 'warning', 'heroicon-o-book-open',
            $endingSoon ? $title.' before the end of term' : $title.' on time',
            'Still with '.$this->names($loans->pluck('student_id')->unique()).'. A book that is lost can be billed to the learner\'s fees.',
            LibraryLoanResource::getUrl())];
    }

    /** @return list<Finding> */
    protected function restingInSickBay(): array
    {
        $visits = SickBayVisit::inSickBay()->where('school_id', $this->schoolId)->whereNotNull('student_id')->get(['student_id']);

        if ($visits->isEmpty()) {
            return [];
        }

        return [$this->item('sick-bay', 'info', 'heroicon-o-heart',
            $visits->count().' '.($visits->count() === 1 ? 'learner is' : 'learners are').' resting in the sick bay',
            $this->names($visits->pluck('student_id')->unique()).'.',
            SickBayVisitResource::getUrl())];
    }

    // ── Stores ──

    /**
     * Store items at or below their warning level, or finished.
     *
     * @return list<Finding>
     */
    protected function storesRunningLow(): array
    {
        $low = StoreItem::where('school_id', $this->schoolId)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('quantity', '<=', 0)->orWhere(fn ($q) => $q->whereNotNull('reorder_level')->whereColumn('quantity', '<=', 'reorder_level')))
            ->orderBy('quantity')
            ->get();

        if ($low->isEmpty()) {
            return [];
        }

        $out = $low->filter(fn (StoreItem $item): bool => (float) $item->quantity <= 0);
        $names = $low->take(4)->map(fn (StoreItem $item): string => $item->name.' ('.$item->amount().')')->implode(', ').($low->count() > 4 ? ' and '.($low->count() - 4).' more' : '');

        return [$this->item('stores-low', $out->isNotEmpty() ? 'danger' : 'warning', 'heroicon-o-archive-box',
            $out->isNotEmpty()
                ? $out->count().' store '.str('item')->plural($out->count()).' finished, '.$low->count().' running low in all'
                : $low->count().' store '.str('item')->plural($low->count()).' running low',
            $names.'. Order more before they run out.',
            StoreItemResource::getUrl(parameters: ['filters' => ['low' => ['value' => true]]]))];
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
