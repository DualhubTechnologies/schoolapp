{{--
    Warns School Admins, on every page, when the subscription is about to
    end, is overdue, or the school is close to its plan's limits.
--}}
@php
    $user = auth()->user();
    // The Windows app's school renews on its Licence page.
    $desktop = \App\Support\Edition::isDesktop();
    $page = $desktop ? \App\Filament\Pages\Licence::class : \App\Filament\Pages\SchoolSubscription::class;
    $show = $user?->school_id && ! $user->hasRole('Super Admin') && \App\Support\Modules::hasFullAccess($user)
        && ! request()->routeIs($page::getRouteName());
    $messages = [];

    if ($show) {
        $st = \App\Services\Subscriptions\SubscriptionManager::status($user->school_id);

        if ($st['state'] === 'grace') {
            $messages[] = ['red', "Your SchoolHub ".($desktop ? 'licence' : 'subscription')." ended on {$st['ends_on']->format('j M Y')}. The system will be locked on {$st['grace_ends_on']->format('j M Y')} unless it is renewed."];
        } elseif ($st['expiring']) {
            $what = $st['state'] === 'trial' ? 'free trial' : ($desktop ? 'licence' : 'subscription');
            $messages[] = ['amber', "Your {$what} ends in {$st['days_left']} " . str('day')->plural($st['days_left']) . " ({$st['ends_on']->format('j M Y')})."];
        }

        foreach (\App\Services\Subscriptions\SubscriptionManager::usage($user->school_id) as $key => $u) {
            if ($u['limit'] && $u['used'] >= $u['limit'] * config('subscriptions.usage_warning', 0.9)) {
                $label = $key === 'students' ? 'active students' : 'staff logins';
                $messages[] = [$u['used'] >= $u['limit'] ? 'red' : 'amber', "You are using {$u['used']} of {$u['limit']} {$label} on your plan."];
            }
        }
    }
@endphp

@foreach ($messages as [$tone, $text])
    <div class="sh-sub-banner sh-sub-{{ $tone }}">
        <span>{{ $text }}</span>
        <a href="{{ $page::getUrl() }}">{{ $desktop ? 'View licence' : 'View subscription' }} →</a>
    </div>
@endforeach

@once
    <style>
        .sh-sub-banner { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .5rem; margin: 0 0 .75rem; padding: .6rem .9rem; border-radius: 10px; font-size: .84rem; font-weight: 600; }
        .sh-sub-banner a { font-weight: 700; text-decoration: underline; white-space: nowrap; }
        .sh-sub-red { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .sh-sub-amber { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
    </style>
@endonce
