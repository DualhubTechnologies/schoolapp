@php
    $children = $this->children();
    $money = fn ($v) => 'UGX '.number_format((float) $v);
@endphp

<x-filament-widgets::widget>
    @if (! $children)
        <x-filament::section>
            <x-slot name="heading">No learners linked to your login yet</x-slot>
            Ask the school office to check that your phone number is on your child's record.
        </x-filament::section>
    @endif

    <div class="pc-grid">
        @foreach ($children as $c)
            @php
                $student = $c['student'];
                $school = $student->school;
                $owing = $c['balance'] > 0;
                $att = $c['attendance'];
                $token = $student->parent_token;
                $initials = collect(preg_split('/\s+/', trim($student->name)))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
            @endphp

            <section class="pc-card">
                <header class="pc-who">
                    @if ($student->photo)
                        <img src="{{ $student->photoUrl() }}" alt="{{ $student->name }}">
                    @else
                        <span class="pc-initials">{{ $initials }}</span>
                    @endif
                    <div>
                        <strong>{{ $student->name }}</strong>
                        <span>{{ trim(($student->schoolClass?->name ?? '').($student->section ? ' '.$student->section->name : '')) }} · {{ $student->admission_no }}</span>
                    </div>
                </header>

                <div @class(['pc-balance', 'is-owing' => $owing, 'is-clear' => ! $owing])>
                    <span>{{ $owing ? 'Fees balance' : ($c['balance'] < 0 ? 'In credit' : 'Fees') }}</span>
                    <strong>{{ $c['balance'] == 0 ? 'Fully paid' : $money(abs($c['balance'])) }}</strong>
                    @if ($c['summary'])
                        <small>{{ $c['term']->label() }}: billed {{ $money($c['summary']['charged']) }}, paid {{ $money($c['summary']['paid']) }}</small>
                    @endif
                </div>

                @if ($owing && ($student->schoolpay_code || $school->fee_payment_bank || $school->fee_payment_mobile_money || $school->fee_payment_instructions))
                    <div class="pc-block">
                        <h3>How to pay</h3>
                        @if ($student->schoolpay_code)
                            <p class="pc-code">SchoolPay code <b>{{ $student->schoolpay_code }}</b></p>
                        @endif
                        @if ($school->fee_payment_mobile_money)<p><b>Mobile money:</b> {{ $school->fee_payment_mobile_money }}</p>@endif
                        @if ($school->fee_payment_bank)<p><b>Bank:</b> {{ $school->fee_payment_bank }}</p>@endif
                        @if ($school->fee_payment_instructions)<p>{!! nl2br(e($school->fee_payment_instructions)) !!}</p>@endif
                    </div>
                @endif

                <div class="pc-block">
                    <h3>Recent payments</h3>
                    @forelse ($c['payments'] as $payment)
                        <a class="pc-row" href="{{ route('parent.receipt', ['token' => $token, 'payment' => $payment->id]) }}" target="_blank">
                            <span>{{ $payment->paid_on?->format('j M Y') }} · {{ $payment->receipt_no }}</span>
                            <b>{{ $money($payment->amount) }}</b>
                        </a>
                    @empty
                        <p class="pc-muted">No payments yet.</p>
                    @endforelse
                </div>

                <div class="pc-block">
                    <h3>Report cards</h3>
                    @forelse ($c['reports'] as $term)
                        <a class="pc-row" href="{{ route('parent.report', ['token' => $token, 'term' => $term->id]) }}" target="_blank">
                            <span>{{ $term->label() }}</span>
                            <b>Open</b>
                        </a>
                    @empty
                        <p class="pc-muted">The school has not released report cards yet.</p>
                    @endforelse
                </div>

                @if ($att && $att['days'] > 0)
                    <div class="pc-block">
                        <h3>Attendance this term</h3>
                        <div class="pc-stats">
                            <div><b>{{ $att['present'] }}</b><span>present</span></div>
                            <div @class(['is-alert' => $att['absent'] > 0])><b>{{ $att['absent'] }}</b><span>absent</span></div>
                            <div><b>{{ $att['late'] }}</b><span>late</span></div>
                        </div>
                    </div>
                @endif

                <a class="pc-full" href="{{ $c['link'] }}" target="_blank">Full fees statement →</a>
            </section>
        @endforeach
    </div>

    <style>
        .pc-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 22rem), 1fr)); }
        .pc-card { background: #fff; border: 1px solid #e4e9f1; border-radius: 1rem; padding: 1.1rem; display: grid; gap: .9rem; align-content: start; }
        .pc-who { display: flex; align-items: center; gap: .8rem; }
        .pc-who img, .pc-initials { width: 3.2rem; height: 3.2rem; border-radius: 50%; object-fit: cover; flex: none; }
        .pc-initials { display: grid; place-items: center; background: #eef5fc; color: #1a5fa8; font-weight: 800; }
        .pc-who strong { display: block; color: #0f1f38; font-size: 1.05rem; }
        .pc-who span { font-size: .8rem; color: #64748b; }
        .pc-balance { text-align: center; border-radius: .9rem; padding: 1rem; }
        .pc-balance.is-owing { background: #fff7ed; border: 1px solid #fed7aa; }
        .pc-balance.is-clear { background: #f0fdf4; border: 1px solid #bbf7d0; }
        .pc-balance span { display: block; font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; color: #64748b; }
        .pc-balance strong { display: block; font-size: 1.7rem; font-weight: 800; margin: .1rem 0; }
        .pc-balance.is-owing strong { color: #c2410c; }
        .pc-balance.is-clear strong { color: #15803d; }
        .pc-balance small { font-size: .78rem; color: #64748b; }
        .pc-block h3 { margin: 0 0 .35rem; font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; color: #64748b; font-weight: 700; }
        .pc-block p { margin: .15rem 0; font-size: .88rem; color: #334155; }
        .pc-code { padding: .5rem .7rem; border: 1px dashed #93c5fd; background: #eff6ff; border-radius: .6rem; }
        .pc-code b { letter-spacing: .06em; user-select: all; }
        .pc-row { display: flex; justify-content: space-between; gap: .75rem; padding: .5rem 0; border-bottom: 1px solid #f0f3f8; font-size: .88rem; color: #334155; }
        .pc-row b { color: #1a5fa8; white-space: nowrap; }
        .pc-row:last-child { border-bottom: 0; }
        .pc-muted { color: #94a3b8 !important; }
        .pc-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; text-align: center; }
        .pc-stats div { background: #f8fafc; border-radius: .6rem; padding: .5rem .25rem; }
        .pc-stats b { display: block; font-size: 1.1rem; color: #0f1f38; }
        .pc-stats span { font-size: .72rem; color: #64748b; }
        .pc-stats .is-alert b { color: #c2410c; }
        .pc-full { font-size: .88rem; font-weight: 600; color: #1a5fa8; }
        .dark .pc-card { background: rgb(255 255 255 / .03); border-color: rgb(255 255 255 / .1); }
        .dark .pc-who strong, .dark .pc-stats b { color: #e2e8f0; }
        .dark .pc-block p, .dark .pc-row { color: #cbd5e1; }
        .dark .pc-stats div { background: rgb(255 255 255 / .05); }
        .dark .pc-row { border-color: rgb(255 255 255 / .08); }
    </style>
</x-filament-widgets::widget>
