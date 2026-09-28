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
        'pending' => ['Awaiting approval', 'sub-blue'],
        'rejected' => ['Not approved', 'sub-red'],
    ][$st['state']];
    $locked = in_array($st['state'], \App\Services\Subscriptions\SubscriptionManager::LOCKED, true);
    $suggested = $this->suggestedPlanId();
@endphp

<x-filament-panels::page>
    @if ($st['state'] === 'pending')
        <div class="sub-alert sub-alert-amber">
            <strong>Your school is waiting for SchoolHub's approval.</strong>
            <div>We check every new school before opening it, usually the same day. We will email {{ auth()->user()->email }} as soon as it is approved; your free trial starts then.</div>
        </div>
    @elseif ($st['state'] === 'rejected')
        <div class="sub-alert sub-alert-red">
            <strong>SchoolHub could not approve this school's registration.</strong>
            @if ($this->school->rejection_reason)
                <div>Reason: {{ $this->school->rejection_reason }}</div>
            @endif
            @if ($pay['contact_phone'] || $pay['contact_email'])
                <div>If you think this is a mistake, contact us on {{ collect([$pay['contact_phone'], $pay['contact_email']])->filter()->implode(' or ') }}.</div>
            @endif
        </div>
    @elseif ($locked)
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

    @if ($isManager)
        <div class="sub-card sub-code-hint">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <div class="sub-label">Have a code from SchoolHub?</div>
                <div class="sub-muted">After you pay, we send you a code by phone or WhatsApp. Choose the plan it's for below, then enter the code to activate straight away — no need to wait for us.</div>
            </div>
        </div>
    @endif

    @unless (in_array($st['state'], ['pending', 'rejected'], true))
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
    @endunless

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
                        <button type="button" wire:click="chooseplan({{ $p->getKey() }})" class="sub-tier-activate">
                            {{ $plan?->is($p) ? 'Renew with a code' : 'Activate with a code' }}
                        </button>
                    </div>
                @endforeach
            </div>
        </div>

        @if ($codeModalPlanId)
            @php $modalPlan = $this->plans->firstWhere('id', $codeModalPlanId); @endphp
            @if ($modalPlan)
                <div class="sub-modal-backdrop" wire:click.self="closeCodeModal">
                    <div class="sub-modal">
                        <button type="button" class="sub-modal-close" wire:click="closeCodeModal" aria-label="Close">&times;</button>
                        <h3>Activate {{ $modalPlan->name }}</h3>
                        <p class="sub-muted">Enter the code SchoolHub sent you for this plan.</p>
                        <form wire:submit.prevent="redeemCode">
                            <input
                                type="text"
                                wire:model="activationCode"
                                placeholder="XXXX-XXXX-XXXX"
                                class="sub-code-input"
                                autocomplete="off"
                                autocapitalize="characters"
                                autofocus
                            >
                            <div class="sub-modal-actions">
                                <button type="button" wire:click="closeCodeModal" class="sub-btn-secondary">Cancel</button>
                                <button type="submit" class="sub-code-btn" wire:loading.attr="disabled" wire:target="redeemCode">
                                    <span wire:loading.remove wire:target="redeemCode">Activate</span>
                                    <span wire:loading wire:target="redeemCode">Activating…</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endif

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
        .sub-code-hint { flex-direction: row; align-items: center; gap: .9rem; border-color: #bfdbfe; background: #f5f9ff; }
        .sub-code-hint svg { width: 1.5rem; height: 1.5rem; color: #1a5fa8; flex: none; }
        .sub-code-input { min-width: 12rem; padding: .55rem .75rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: .95rem; font-family: ui-monospace, monospace; text-transform: uppercase; letter-spacing: .04em; }
        .sub-code-input:focus { outline: 2px solid #1a5fa8; outline-offset: 1px; }
        .sub-code-btn { padding: .55rem 1.1rem; border: 0; border-radius: 8px; background: #1a5fa8; color: #fff; font-weight: 700; font-size: .88rem; cursor: pointer; }
        .sub-code-btn:hover { background: #164e87; }
        .sub-code-btn[disabled] { opacity: .6; cursor: default; }
        .sub-tier-activate { margin-top: .4rem; padding: .5rem .75rem; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; color: #1a5fa8; font-weight: 700; font-size: .8rem; cursor: pointer; }
        .sub-tier-activate:hover { background: #f5f9ff; border-color: #1a5fa8; }
        .sub-modal-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, .55); display: flex; align-items: center; justify-content: center; z-index: 60; padding: 1rem; }
        .sub-modal { position: relative; width: 100%; max-width: 26rem; background: #fff; border-radius: 14px; padding: 1.5rem; box-shadow: 0 20px 50px rgba(0, 0, 0, .25); }
        .sub-modal h3 { font-size: 1.05rem; font-weight: 800; color: #16233a; margin-bottom: .3rem; }
        .sub-modal form { margin-top: 1rem; }
        .sub-modal .sub-code-input { width: 100%; box-sizing: border-box; }
        .sub-modal-actions { display: flex; justify-content: flex-end; gap: .6rem; margin-top: 1rem; }
        .sub-modal-close { position: absolute; top: .7rem; right: .7rem; width: 1.75rem; height: 1.75rem; border: 0; background: transparent; font-size: 1.35rem; line-height: 1; color: #94a3b8; cursor: pointer; }
        .sub-modal-close:hover { color: #16233a; }
        .sub-btn-secondary { padding: .55rem 1rem; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; color: #334155; font-weight: 600; font-size: .85rem; cursor: pointer; }
        .sub-btn-secondary:hover { background: #f8fafc; }
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
