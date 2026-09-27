<?php

namespace App\Services;

use App\Filament\Admin\Resources\DemoRequests\DemoRequestResource;
use App\Filament\Admin\Resources\Schools\SchoolResource;
use App\Filament\App\Resources\FeeBalances\FeeBalanceResource;
use App\Filament\App\Resources\PayrollPeriods\PayrollPeriodResource;
use App\Filament\App\Resources\SalaryArrears\SalaryArrearResource;
use App\Filament\App\Resources\Terms\TermResource;
use App\Filament\Pages\SchoolSubscription;
use App\Filament\Widgets\SetupChecklist;
use App\Models\DemoRequest;
use App\Models\PayrollPeriod;
use App\Models\SalaryArrear;
use App\Models\School;
use App\Models\Staff;
use App\Models\Term;
use App\Models\User;
use App\Services\Dashboard\SchoolFigures;
use App\Services\Subscriptions\SubscriptionManager;
use App\Support\Modules;
use Illuminate\Support\Facades\Cache;

/**
 * What needs someone's attention, for the bell in the topbar. Each item
 * links straight to the page where it is dealt with.
 *
 * Only what the user may act on is included: fee items need the Fees
 * module, payroll items HR, subscription items full access, platform
 * items Super Admin. Results are cached briefly per user so the bell does
 * not re-run these queries on every page.
 */
class AttentionItems
{
    /** Seconds a user's list is reused before it is worked out again. */
    public const CACHE_SECONDS = 60;

    /**
     * @return list<array{key: string, tone: 'danger'|'warning'|'info', icon: string, title: string, detail: ?string, url: string}>
     */
    public static function for(User $user): array
    {
        return Cache::remember(static::cacheKey($user), static::CACHE_SECONDS, function () use ($user) {
            $items = [
                ...($user->hasRole('Super Admin') ? static::platform() : []),
                ...($user->school_id ? static::school($user) : []),
            ];

            // Most urgent first.
            $rank = ['danger' => 0, 'warning' => 1, 'info' => 2];
            usort($items, fn ($a, $b) => $rank[$a['tone']] <=> $rank[$b['tone']]);

            return $items;
        });
    }

    /** Clear a user's cached list, e.g. after something was dealt with. */
    public static function forget(User $user): void
    {
        Cache::forget(static::cacheKey($user));
    }

    protected static function cacheKey(User $user): string
    {
        return "attention-items:{$user->getKey()}";
    }

    // ── A school's own items ──

    protected static function school(User $user): array
    {
        $schoolId = (int) $user->school_id;
        $items = [];
        $fullAccess = Modules::hasFullAccess($user);

        if ($fullAccess) {
            $items = [...$items, ...static::subscription($schoolId)];

            if ($user->hasRole('School Admin')) {
                $steps = collect((new SetupChecklist)->steps());
                $done = $steps->where('done', true)->count();

                if ($done < $steps->count()) {
                    $next = $steps->firstWhere('done', false);
                    $items[] = static::item('setup', 'info', 'heroicon-o-rocket-launch',
                        "Finish setting up — {$done} of {$steps->count()} done",
                        "Next: {$next['title']}", $next['url']);
                }
            }
        }

        if (Modules::allows('academics') && Term::where('school_id', $schoolId)->exists() && ! Term::current($schoolId)) {
            $items[] = static::item('term', 'warning', 'heroicon-o-calendar',
                'No current term is set',
                'Fees, marks and reports need a current term.', TermResource::getUrl(panel: 'app'));
        }

        if (Modules::allows('fees')) {
            [$owed, $debtors] = SchoolFigures::outstanding($schoolId);

            if ($debtors > 0) {
                $items[] = static::item('fees', 'info', 'heroicon-o-banknotes',
                    number_format($debtors).' '.str('student')->plural($debtors).' with fee balances',
                    'UGX '.number_format($owed).' outstanding in total', FeeBalanceResource::getUrl(panel: 'app'));
            }
        }

        if (Modules::allows('hr')) {
            $items = [...$items, ...static::payroll($schoolId)];
        }

        return $items;
    }

    protected static function subscription(int $schoolId): array
    {
        $items = [];
        $url = SchoolSubscription::getUrl(panel: 'app');
        $st = SubscriptionManager::status($schoolId);

        if ($st['state'] === 'grace') {
            $items[] = static::item('subscription', 'danger', 'heroicon-o-exclamation-triangle',
                'Subscription has ended',
                'Renew before '.$st['grace_ends_on']->format('j M').' to avoid the system locking.', $url);
        } elseif ($st['expiring']) {
            $what = $st['state'] === 'trial' ? 'Free trial' : 'Subscription';
            $items[] = static::item('subscription', $st['days_left'] <= 3 ? 'danger' : 'warning', 'heroicon-o-clock',
                "{$what} ends in {$st['days_left']} ".str('day')->plural($st['days_left']),
                'Ends '.$st['ends_on']->format('j M Y').'. Choose a plan to keep going.', $url);
        }

        foreach (SubscriptionManager::usage($schoolId) as $key => $u) {
            if ($u['limit'] && $u['used'] >= $u['limit'] * config('subscriptions.usage_warning', 0.9)) {
                $label = $key === 'students' ? 'active students' : 'staff logins';
                $items[] = static::item("usage-{$key}", $u['used'] >= $u['limit'] ? 'danger' : 'warning', 'heroicon-o-chart-bar',
                    "{$u['used']} of {$u['limit']} {$label} used",
                    $u['used'] >= $u['limit'] ? 'Your plan limit is reached.' : 'You are close to your plan limit.', $url);
            }
        }

        return $items;
    }

    protected static function payroll(int $schoolId): array
    {
        $items = [];

        $open = PayrollPeriod::where('school_id', $schoolId)
            ->whereIn('status', ['draft', 'approved'])
            ->orderBy('year')->orderBy('month')
            ->get();

        foreach ($open as $period) {
            $items[] = $period->status === 'draft'
                ? static::item("payroll-{$period->id}", 'warning', 'heroicon-o-document-check',
                    "Payroll for {$period->period_label} needs approval", 'It is still a draft.',
                    PayrollPeriodResource::getUrl('edit', ['record' => $period], panel: 'app'))
                : static::item("payroll-{$period->id}", 'warning', 'heroicon-o-credit-card',
                    "Payroll for {$period->period_label} is not marked paid", 'Approved, awaiting payment.',
                    PayrollPeriodResource::getUrl('edit', ['record' => $period], panel: 'app'));
        }

        // Towards month end, remind if this month's payroll has not been started.
        if (today()->day >= 25
            && Staff::where('school_id', $schoolId)->where('status', 'active')->exists()
            && ! PayrollPeriod::where('school_id', $schoolId)->where('year', today()->year)->where('month', today()->month)->exists()) {
            $items[] = static::item('payroll-due', 'info', 'heroicon-o-calendar-days',
                today()->format('F').' payroll has not been prepared', 'Salaries are due at the end of the month.',
                PayrollPeriodResource::getUrl(panel: 'app'));
        }

        $arrears = SalaryArrear::where('school_id', $schoolId)->where('status', 'pending')->count();

        if ($arrears > 0) {
            $items[] = static::item('arrears', 'warning', 'heroicon-o-exclamation-circle',
                $arrears.' salary '.str('arrear')->plural($arrears).' pending review', null,
                SalaryArrearResource::getUrl(panel: 'app'));
        }

        return $items;
    }

    // ── Platform owner's items ──

    protected static function platform(): array
    {
        $items = [];
        $schoolsUrl = SchoolResource::getUrl(panel: 'admin');

        $newDemos = DemoRequest::where('status', 'new')->count();

        if ($newDemos > 0) {
            $latest = DemoRequest::where('status', 'new')->latest()->first();
            $items[] = static::item('demos', 'warning', 'heroicon-o-calendar-days',
                $newDemos.' new demo '.str('request')->plural($newDemos),
                'Latest: '.$latest->school_name.' · '.$latest->created_at->diffForHumans(),
                DemoRequestResource::getUrl(panel: 'admin'));
        }

        $states = School::where('status', '!=', 'rejected')->get()
            ->map(fn (School $s) => SubscriptionManager::status($s) + ['school' => $s]);

        $grace = $states->where('state', 'grace');
        if ($grace->isNotEmpty()) {
            $items[] = static::item('schools-grace', 'danger', 'heroicon-o-exclamation-triangle',
                $grace->count().' '.str('school')->plural($grace->count()).' overdue',
                $grace->take(3)->map(fn ($s) => $s['school']->name)->implode(', '), $schoolsUrl);
        }

        $expiring = $states->filter(fn ($s) => $s['expiring']);
        if ($expiring->isNotEmpty()) {
            $items[] = static::item('schools-expiring', 'warning', 'heroicon-o-clock',
                $expiring->count().' '.str('school')->plural($expiring->count()).' ending within '.config('subscriptions.warn_days').' days',
                $expiring->take(3)->map(fn ($s) => $s['school']->name)->implode(', '), $schoolsUrl);
        }

        $locked = $states->whereIn('state', ['expired', 'none']);
        if ($locked->isNotEmpty()) {
            $items[] = static::item('schools-locked', 'info', 'heroicon-o-lock-closed',
                $locked->count().' '.str('school')->plural($locked->count()).' locked (not renewed)',
                $locked->take(3)->map(fn ($s) => $s['school']->name)->implode(', '), $schoolsUrl);
        }

        $newSchools = School::where('created_at', '>=', now()->subDays(7))->count();
        if ($newSchools > 0) {
            $items[] = static::item('schools-new', 'info', 'heroicon-o-building-office-2',
                $newSchools.' new '.str('school')->plural($newSchools).' this week',
                'Registered themselves and started a trial.', $schoolsUrl);
        }

        return $items;
    }

    protected static function item(string $key, string $tone, string $icon, string $title, ?string $detail, string $url): array
    {
        return compact('key', 'tone', 'icon', 'title', 'detail', 'url');
    }
}
