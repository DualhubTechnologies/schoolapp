@php
    $student = $this->selectedStudent;
    $account = $this->account;
    $issued  = $this->issuedPayment;
    $money   = fn ($v) => 'UGX ' . number_format((float) $v, 0);
@endphp

<x-filament-panels::page>
    <div class="rp-grid">

        {{-- ── Left: the form ── --}}
        <form wire:submit="save" class="rp-main">
            {{ $this->form }}

            @if ($student)
                <div class="rp-actions">
                    <x-filament::button type="submit" size="lg" icon="heroicon-o-check" wire:loading.attr="disabled">
                        Save &amp; issue receipt
                    </x-filament::button>
                    @if ($account['balance'] > 0)
                        <x-filament::button color="gray" wire:click="fillBalance" type="button">
                            Pay full balance ({{ number_format($account['balance']) }})
                        </x-filament::button>
                    @endif
                </div>
            @endif
        </form>

        {{-- ── Right: who is paying, and what they owe ── --}}
        <aside class="rp-side">

            @if ($issued)
                <div class="rp-card rp-issued">
                    <div class="rp-issued-icon">✓</div>
                    <div class="rp-label">Receipt issued</div>
                    <div class="rp-receipt-no">{{ $issued->receipt_no }}</div>
                    <div class="rp-issued-amount">{{ $money($issued->amount) }}</div>
                    <div class="rp-muted">{{ $issued->student?->name }}</div>
                    <div class="rp-issued-actions">
                        <x-filament::button tag="a" :href="\App\Filament\Pages\ReceivePayment::receiptUrl($issued, true)" target="_blank" icon="heroicon-o-printer">
                            Print receipt
                        </x-filament::button>
                        <x-filament::button color="gray" wire:click="startNew" icon="heroicon-o-plus">
                            Next student
                        </x-filament::button>
                    </div>
                </div>
            @endif

            @if ($student)
                <div class="rp-card">
                    <div class="rp-person">
                        <img src="{{ $student->photoUrl() }}" alt="">
                        <div>
                            <div class="rp-name">{{ $student->name ?: 'No name on record' }}</div>
                            <div class="rp-muted">
                                {{ $student->admission_no }} ·
                                {{ $student->schoolClass?->name }}{{ $student->section ? ' · ' . $student->section->name : '' }}
                                @if ($student->residencyType) · {{ $student->residencyType->name }} @endif
                            </div>
                            @if ($student->guardian)
                                <div class="rp-muted">Guardian: {{ $student->guardian->name }} · {{ $student->guardian->phone }}</div>
                            @endif
                        </div>
                    </div>

                    <div class="rp-balance {{ $account['balance'] > 0 ? 'is-owing' : 'is-clear' }}">
                        <div class="rp-label">{{ $account['balance'] < 0 ? 'In credit' : 'Balance owed' }}</div>
                        <div class="rp-balance-figure">{{ $money(abs($account['balance'])) }}</div>
                        @if ($account['balance'] == 0)
                            <div class="rp-muted">Fully paid</div>
                        @endif
                    </div>

                    <dl class="rp-stats">
                        <div><dt>Billed this term</dt><dd>{{ $money($account['term_billed']) }}</dd></div>
                        <div><dt>Paid this term</dt><dd>{{ $money($account['term_paid']) }}</dd></div>
                    </dl>

                    @if ($account['recent']->isNotEmpty())
                        <div class="rp-label rp-recent-title">Recent payments</div>
                        <ul class="rp-recent">
                            @foreach ($account['recent'] as $p)
                                <li>
                                    <span>{{ $p->paid_on?->format('j M') }} · {{ $p->methodLabel() }}</span>
                                    <a href="{{ \App\Filament\Pages\ReceivePayment::receiptUrl($p) }}" target="_blank">{{ $p->receipt_no }}</a>
                                    <strong>{{ number_format((float) $p->amount) }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <a class="rp-link" href="{{ \App\Filament\Pages\StudentAccount::getUrl(['student' => $student->getKey()]) }}">
                        Open full account →
                    </a>
                </div>
            @elseif (! $issued)
                <div class="rp-card rp-empty">
                    <x-filament::icon icon="heroicon-o-user-circle" class="rp-empty-icon" />
                    <p>Search for a student to see their balance and record a payment.</p>
                </div>
            @endif
        </aside>
    </div>

    <style>
        .rp-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.5rem; align-items: start; }
        @media (min-width: 1024px) { .rp-grid { grid-template-columns: minmax(0, 1fr) 22rem; } }
        .rp-main { display: flex; flex-direction: column; gap: 1.5rem; }
        .rp-actions { display: flex; flex-wrap: wrap; gap: .75rem; }
        .rp-side { display: flex; flex-direction: column; gap: 1rem; position: sticky; top: 5.5rem; }

        .rp-card { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; padding: 1.25rem; }
        .rp-muted { font-size: .8rem; color: #64748b; }
        .rp-label { font-size: .7rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #64748b; }

        .rp-person { display: flex; gap: .85rem; align-items: center; }
        .rp-person img { width: 3.25rem; height: 3.25rem; border-radius: 999px; object-fit: cover; flex-shrink: 0; }
        .rp-name { font-weight: 700; font-size: 1rem; color: #16233a; }

        .rp-balance { margin: 1rem 0; padding: .9rem 1rem; border-radius: 10px; }
        .rp-balance.is-owing { background: #fef2f2; }
        .rp-balance.is-owing .rp-balance-figure { color: #b91c1c; }
        .rp-balance.is-clear { background: #f0fdf4; }
        .rp-balance.is-clear .rp-balance-figure { color: #15803d; }
        .rp-balance-figure { font-size: 1.6rem; font-weight: 800; font-variant-numeric: tabular-nums; }

        .rp-stats { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
        .rp-stats dt { font-size: .72rem; color: #64748b; }
        .rp-stats dd { font-weight: 700; font-variant-numeric: tabular-nums; }

        .rp-recent-title { margin-top: 1.1rem; margin-bottom: .35rem; }
        .rp-recent li { display: grid; grid-template-columns: 1fr auto auto; gap: .6rem; font-size: .8rem; padding: .35rem 0; border-top: 1px solid #f1f5f9; }
        .rp-recent a { color: #1a5fa8; font-weight: 600; }
        .rp-recent strong { font-variant-numeric: tabular-nums; }

        .rp-link { display: inline-block; margin-top: 1rem; font-size: .85rem; font-weight: 600; color: #1a5fa8; }

        .rp-issued { text-align: center; border-color: #86efac; background: #f0fdf4; }
        .rp-issued-icon { width: 2.5rem; height: 2.5rem; margin: 0 auto .5rem; border-radius: 999px; background: #16a34a; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; }
        .rp-receipt-no { font-size: 1.25rem; font-weight: 800; color: #16233a; letter-spacing: .03em; }
        .rp-issued-amount { font-size: 1.1rem; font-weight: 700; color: #15803d; margin: .15rem 0; }
        .rp-issued-actions { display: flex; justify-content: center; flex-wrap: wrap; gap: .5rem; margin-top: 1rem; }

        .rp-empty { text-align: center; color: #64748b; font-size: .9rem; padding: 2rem 1.25rem; }
        .rp-empty-icon { width: 2.75rem; height: 2.75rem; margin: 0 auto .5rem; color: #cbd5e1; }
    </style>
</x-filament-panels::page>
