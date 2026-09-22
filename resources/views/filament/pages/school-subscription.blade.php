@php
    $st = $this->status;
    $usage = $this->usage;
    $plan = $st['plan'];
    $isManager = $this->isManager();
    $pay = config('subscriptions.payment');
    $money = fn ($v) => 'UGX ' . number_format((float) $v);
    $stateInfo = [
        'trial' => ['Free trial', 'sub-blue'],
        'active' => ['Active', 'sub-green'],
        'grace' => ['Payment overdue', 'sub-red'],
        'expired' => ['Expired', 'sub-red'],
        'none' => ['No subscription', 'sub-red'],
        'suspended' => ['Suspended', 'sub-red'],
    ][$st['state']];
    $locked = in_array($st['state'], \App\Services\Subscriptions\SubscriptionManager::LOCKED, true);
    $suggested = $this->suggestedPlanId();
@endphp

<x-filament-panels::page>
    @if ($locked)
        <div class="sub-alert sub-alert-red">
            <strong>
                @if ($st['state'] === 'suspended')
                    This school's SchoolHub account has been suspended.
                @elseif ($st['state'] === 'none')
                    This school does not have a SchoolHub subscription yet.
                @else
                    This school's SchoolHub subscription ended on {{ $st['ends_on']?->format('j M Y') }}.
                @endif
            </strong>
            <div>
                {{ $isManager ? 'Pay using the details below; the system opens again as soon as your payment is recorded.' : 'Please ask the head teacher or school administrator to renew it.' }}
                All your records are safe — nothing has been deleted.
            </div>
        </div>
    @elseif ($st['state'] === 'grace')
        <div class="sub-alert sub-alert-red">
            <strong>Your subscription ended on {{ $st['ends_on']->format('j M Y') }}.</strong>
            <div>The system keeps working until {{ $st['grace_ends_on']->format('j M Y') }}. Renew before then to avoid interruption.</div>
        </div>
    @elseif ($st['expiring'])
        <div class="sub-alert sub-alert-amber">
            <strong>{{ $st['state'] === 'trial' ? 'Your free trial' : 'Your subscription' }} ends in {{ $st['days_left'] }} {{ str('day')->plural($st['days_left']) }} ({{ $st['ends_on']->format('j M Y') }}).</strong>
            <div>Renew now so the school is not interrupted.</div>
        </div>
    @endif

    <div class="sub-grid">
        <div class="sub-card">
            <div class="sub-label">Current plan</div>
            <div class="sub-plan">{{ $plan?->name ?? '—' }} <span class="sub-badge {{ $stateInfo[1] }}">{{ $stateInfo[0] }}</span></div>
            @if ($st['ends_on'])
                <div class="sub-muted">
                    {{ $locked || $st['state'] === 'grace' ? 'Ended' : 'Paid up to' }} <strong>{{ $st['ends_on']->format('j M Y') }}</strong>
                    @if (! $locked && $st['state'] !== 'grace' && $st['days_left'] !== null)
                        · {{ $st['days_left'] }} {{ str('day')->plural($st['days_left']) }} left
                    @endif
                </div>
            @endif
            <div class="sub-muted">Every plan includes every module: students, fees, finance, exams and report cards, HR & payroll, promotion.</div>
        </div>

        @foreach (['students' => 'Active students', 'users' => 'Staff logins'] as $key => $label)
            @php
                $used = $usage[$key]['used'];
                $limit = $usage[$key]['limit'];
                $pct = $limit ? min(100, round($used / max($limit, 1) * 100)) : 0;
                $tone = $limit === null ? 'sub-bar-blue' : ($pct >= 100 ? 'sub-bar-red' : ($pct >= config('subscriptions.usage_warning') * 100 ? 'sub-bar-amber' : 'sub-bar-blue'));
            @endphp
            <div class="sub-card">
                <div class="sub-label">{{ $label }}</div>
                <div class="sub-plan">{{ number_format($used) }} <span class="sub-of">of {{ \App\Models\Plan::limitLabel($limit) }}</span></div>
                <div class="sub-bar"><span class="{{ $tone }}" style="width: {{ $limit === null ? 8 : max(2, $pct) }}%"></span></div>
                <div class="sub-muted">
                    @if ($key === 'students')
                        Students marked Withdrawn, Transferred or Completed do not count.
                    @else
                        Parents and students do not count.
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if ($isManager)
        <div class="sub-section">
            <h3>Plans</h3>
            <div class="sub-muted" style="margin-bottom:.8rem">Choose by the number of active students and staff logins. Paying for a year saves about 10%.</div>
            <div class="sub-plans">
                @foreach ($this->plans as $p)
                    <div @class(['sub-tier', 'sub-tier-current' => $plan?->is($p), 'sub-tier-suggested' => ! $plan?->is($p) && $p->getKey() === $suggested])>
                        @if ($plan?->is($p))
                            <div class="sub-ribbon">Your plan</div>
                        @elseif ($p->getKey() === $suggested)
                            <div class="sub-ribbon sub-ribbon-green">Fits your school</div>
                        @endif
                        <div class="sub-tier-name">{{ $p->name }}</div>
                        <div class="sub-tier-price">{{ $money($p->price_per_term) }}<span> / term</span></div>
                        <div class="sub-muted">or {{ $money($p->price_per_year) }} / year</div>
                        <ul>
                            <li><strong>{{ \App\Models\Plan::limitLabel($p->max_students) }}</strong> active students</li>
                            <li><strong>{{ \App\Models\Plan::limitLabel($p->max_users) }}</strong> staff logins</li>
                            <li>All modules included</li>
                        </ul>
                        @if ($p->description)<div class="sub-muted">{{ $p->description }}</div>@endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="sub-grid sub-grid-2">
            <div class="sub-card">
                <div class="sub-label">How to pay</div>
                <dl class="sub-dl">
                    @if ($pay['mobile_money'])<dt>MTN Mobile Money</dt><dd>{{ $pay['mobile_money'] }}</dd>@endif
                    @if ($pay['airtel_money'])<dt>Airtel Money</dt><dd>{{ $pay['airtel_money'] }}</dd>@endif
                    @if ($pay['bank'])<dt>Bank</dt><dd>{{ $pay['bank'] }}</dd>@endif
                    <dt>Reference</dt><dd><strong>{{ $this->school->unique_code ?: $this->school->name }}</strong> (your school code)</dd>
                    @if ($pay['contact_phone'] || $pay['contact_email'])
                        <dt>After paying</dt><dd>Send the transaction ID to {{ collect([$pay['contact_phone'], $pay['contact_email']])->filter()->implode(' or ') }}. Your subscription is updated the same day.</dd>
                    @endif
                </dl>
                @if (! $pay['mobile_money'] && ! $pay['airtel_money'] && ! $pay['bank'])
                    <div class="sub-muted">Contact SchoolHub for payment details.</div>
                @endif
            </div>

            <div class="sub-card">
                <div class="sub-label">Payment history</div>
                @forelse ($this->payments as $payment)
                    <div class="sub-pay">
                        <div>
                            <strong>{{ $money($payment->amount) }}</strong>
                            <div class="sub-muted">{{ \App\Models\SubscriptionPayment::METHODS[$payment->method] ?? $payment->method }}{{ $payment->reference ? ' · ' . $payment->reference : '' }}</div>
                        </div>
                        <div class="sub-right">
                            {{ $payment->paid_on->format('j M Y') }}
                            @if ($payment->subscription)
                                <div class="sub-muted">{{ $payment->subscription->plan?->name }} · {{ $payment->subscription->periodLabel() }}</div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="sub-muted">No payments yet.</div>
                @endforelse
            </div>
        </div>
    @endif

    <style>
        .sub-alert { border-radius: 12px; padding: .9rem 1.1rem; font-size: .88rem; line-height: 1.5; }
        .sub-alert-red { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .sub-alert-amber { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
        .sub-grid { display: grid; grid-template-columns: 1.4fr 1fr 1fr; gap: 1rem; }
        .sub-grid-2 { grid-template-columns: 1fr 1fr; }
        @media (max-width: 1024px) { .sub-grid, .sub-grid-2 { grid-template-columns: 1fr; } }
        .sub-card { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; padding: 1.1rem 1.2rem; display: flex; flex-direction: column; gap: .45rem; }
        .sub-label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #64748b; }
        .sub-plan { font-size: 1.45rem; font-weight: 800; color: #16233a; display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
        .sub-of { font-size: .9rem; font-weight: 600; color: #64748b; }
        .sub-muted { color: #64748b; font-size: .8rem; line-height: 1.45; }
        .sub-badge { font-size: .7rem; font-weight: 700; padding: .2rem .55rem; border-radius: 999px; }
        .sub-blue { background: #e0edff; color: #1a5fa8; }
        .sub-green { background: #dcfce7; color: #166534; }
        .sub-red { background: #fee2e2; color: #991b1b; }
        .sub-bar { height: 8px; background: #eef2f7; border-radius: 999px; overflow: hidden; }
        .sub-bar span { display: block; height: 100%; border-radius: 999px; }
        .sub-bar-blue { background: #1a5fa8; }
        .sub-bar-amber { background: #d97706; }
        .sub-bar-red { background: #dc2626; }
        .sub-section { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; padding: 1.1rem 1.2rem; }
        .sub-section h3 { font-size: 1rem; font-weight: 700; color: #16233a; }
        .sub-plans { display: grid; grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr)); gap: .9rem; }
        .sub-tier { position: relative; border: 1px solid #e4e8f0; border-radius: 12px; padding: 1.1rem 1rem .9rem; display: flex; flex-direction: column; gap: .35rem; }
        .sub-tier-current { border: 2px solid #1a5fa8; background: #f5f9ff; }
        .sub-tier-suggested { border: 2px solid #16a34a; }
        .sub-ribbon { position: absolute; top: -.65rem; left: 1rem; background: #1a5fa8; color: #fff; font-size: .66rem; font-weight: 700; padding: .15rem .55rem; border-radius: 999px; }
        .sub-ribbon-green { background: #16a34a; }
        .sub-tier-name { font-weight: 800; color: #16233a; }
        .sub-tier-price { font-size: 1.15rem; font-weight: 800; color: #1a5fa8; }
        .sub-tier-price span { font-size: .78rem; font-weight: 600; color: #64748b; }
        .sub-tier ul { font-size: .82rem; color: #374151; margin: .3rem 0; display: flex; flex-direction: column; gap: .2rem; }
        .sub-tier li::before { content: "✓ "; color: #16a34a; font-weight: 700; }
        .sub-dl { display: grid; grid-template-columns: 9rem 1fr; gap: .45rem .8rem; font-size: .85rem; }
        .sub-dl dt { color: #64748b; }
        .sub-dl dd { color: #16233a; }
        .sub-pay { display: flex; justify-content: space-between; gap: 1rem; padding: .55rem 0; border-bottom: 1px solid #f1f5f9; font-size: .85rem; }
        .sub-pay:last-child { border-bottom: 0; }
        .sub-right { text-align: right; }
    </style>
</x-filament-panels::page>
