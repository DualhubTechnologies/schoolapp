{{--
    Student Account — ledger, statement, invoice for one student.
    All three read the same derived ledger, so they cannot disagree.
    Styled with the scoped .sa-* rules at the bottom.
--}}

@php
    $student = $this->student;
    $term    = $this->term;
    $entries = $this->entries;
    $summary = $this->summary;
    $termSum = $this->termSummary;
    $school  = $student?->school;
    $n       = fn ($v) => number_format((float) $v, 0);
    $date    = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('j M Y') : '—';
    $logo    = $school?->logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($school->logo) : null;
    $photo   = $student?->photoUrl() ?? asset('images/student-avatar.svg');
@endphp

<x-filament-panels::page>

    <div class="sa-no-print">
        {{ $this->form }}
    </div>

    @if (! $student)
        <div class="sa-empty">
            <x-filament::icon icon="heroicon-o-magnifying-glass" class="sa-empty-icon" />
            <p>Search for a student above to open their fee account.</p>
            <p class="sa-muted">Or go to <a href="{{ \App\Filament\App\Resources\FeeBalances\FeeBalanceResource::getUrl() }}">Student Accounts</a> to see everyone who owes.</p>
        </div>
    @else

        {{-- ── Profile + key figures (screen only) ── --}}
        <section class="sa-card sa-no-print">
            <div class="sa-profile">
                <img src="{{ $photo }}" alt="" class="sa-photo">
                <div class="sa-who">
                    <div class="sa-name">{{ $student->name ?: 'No name on record' }}</div>
                    <div class="sa-muted">
                        {{ $student->admission_no }}
                        · {{ $student->schoolClass?->name }}{{ $student->section ? ' · ' . $student->section->name : '' }}
                        @if ($student->residencyType) · {{ $student->residencyType->name }} @endif
                    </div>
                    <div class="sa-muted">
                        Guardian: {{ $student->guardian?->name ?? 'not linked' }}
                        @if ($student->guardian?->phone) · {{ $student->guardian->phone }} @endif
                    </div>
                </div>
                <span @class(['sa-pill', 'is-good' => $student->enrolment_status === 'confirmed', 'is-warn' => $student->enrolment_status !== 'confirmed'])>
                    {{ \App\Models\Student::ENROLMENT_STATUSES[$student->enrolment_status] ?? $student->enrolment_status }}
                </span>
            </div>

            <div class="sa-tiles">
                <div class="sa-tile"><span>Total charged</span><strong>UGX {{ $n($summary['charged']) }}</strong></div>
                <div class="sa-tile"><span>Total paid</span><strong class="is-good">UGX {{ $n($summary['paid']) }}</strong></div>
                <div class="sa-tile">
                    <span>{{ $summary['balance'] < 0 ? 'In credit' : 'Balance owed' }}</span>
                    <strong @class(['is-bad' => $summary['balance'] > 0, 'is-good' => $summary['balance'] <= 0])>UGX {{ $n(abs($summary['balance'])) }}</strong>
                </div>
                <div class="sa-tile">
                    <span>Paid so far</span>
                    <strong>{{ $summary['percent'] }}%</strong>
                    <div class="sa-bar"><div style="width: {{ min(100, max(0, $summary['percent'])) }}%"></div></div>
                </div>
            </div>
        </section>

        {{-- ── View switcher ── --}}
        <div class="sa-no-print">
            <x-filament::tabs>
                <x-filament::tabs.item :active="$this->mode === 'ledger'" wire:click="setMode('ledger')" icon="heroicon-o-list-bullet">
                    Ledger
                </x-filament::tabs.item>
                <x-filament::tabs.item :active="$this->mode === 'statement'" wire:click="setMode('statement')" icon="heroicon-o-document-text">
                    Term statement
                </x-filament::tabs.item>
                <x-filament::tabs.item :active="$this->mode === 'invoice'" wire:click="setMode('invoice')" icon="heroicon-o-document-currency-dollar">
                    Invoice
                </x-filament::tabs.item>
            </x-filament::tabs>
        </div>

        {{-- ── The document (what prints) ── --}}
        <article class="sa-paper">
            <header class="sa-doc-head">
                <div class="sa-doc-school">
                    @if ($logo)<img src="{{ $logo }}" alt="">@endif
                    <div>
                        <h2>{{ $school?->name }}</h2>
                        @if ($school?->address)<p>{{ $school->address }}</p>@endif
                        <p>{{ collect([$school?->phone, $school?->email])->filter()->implode(' · ') }}</p>
                    </div>
                </div>
                <div class="sa-doc-title">
                    <h3>{{ ['ledger' => 'Student ledger', 'statement' => 'Fee statement', 'invoice' => 'Invoice'][$this->mode] }}</h3>
                    @if ($this->mode === 'statement' && $term)<p>{{ $term->label() }}</p>@endif
                    <p>Printed {{ now()->format('j M Y') }}</p>
                </div>
            </header>

            <dl class="sa-doc-student">
                <div><dt>Student</dt><dd>{{ $student->name }}</dd></div>
                <div><dt>Admission no.</dt><dd>{{ $student->admission_no }}</dd></div>
                <div><dt>Class</dt><dd>{{ $student->schoolClass?->name ?? '—' }}{{ $student->section ? ' · ' . $student->section->name : '' }}</dd></div>
                <div><dt>Guardian</dt><dd>{{ $student->guardian?->name ?? '—' }}</dd></div>
            </dl>

            {{-- ══ INVOICE ══ --}}
            @if ($this->mode === 'invoice')
                <table class="sa-table">
                    <tbody>
                        <tr><td>Total charged to date</td><td class="sa-num">UGX {{ $n($summary['charged']) }}</td></tr>
                        <tr><td>Less payments received</td><td class="sa-num is-good">− UGX {{ $n($summary['paid']) }}</td></tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>{{ $summary['balance'] < 0 ? 'Credit on account' : 'Balance now due' }}</td>
                            <td @class(['sa-num', 'sa-big', 'is-bad' => $summary['balance'] > 0, 'is-good' => $summary['balance'] <= 0])>UGX {{ $n(abs($summary['balance'])) }}</td>
                        </tr>
                    </tfoot>
                </table>
                <p class="sa-note">Pay at the bursar's office, by bank deposit, SchoolPay or mobile money, quoting admission number <strong>{{ $student->admission_no }}</strong>.</p>
            @endif

            {{-- ══ STATEMENT ══ --}}
            @if ($this->mode === 'statement')
                <table class="sa-table">
                    <thead>
                        <tr><th>Date</th><th>Details</th><th class="sa-num">Charged</th><th class="sa-num">Paid</th></tr>
                    </thead>
                    <tbody>
                        <tr class="sa-bf">
                            <td>—</td>
                            <td>Balance brought forward</td>
                            <td class="sa-num">{{ $termSum['opening'] != 0 ? $n($termSum['opening']) : '—' }}</td>
                            <td></td>
                        </tr>
                        @forelse ($entries as $entry)
                            <tr>
                                <td class="sa-nowrap">{{ $date($entry['date']) }}</td>
                                <td>
                                    {{ $entry['description'] }}
                                    @if ($entry['reference'])<span class="sa-sub">{{ $entry['reference'] }}</span>@endif
                                    @if ($entry['discount'] > 0)<span class="sa-sub is-good">Less {{ $entry['discount_reason'] ?: 'discount' }}: {{ $n($entry['discount']) }}</span>@endif
                                </td>
                                <td class="sa-num">{{ $entry['debit'] > 0 ? $n($entry['debit']) : '' }}</td>
                                <td class="sa-num is-good">{{ $entry['credit'] > 0 ? $n($entry['credit']) : '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="sa-none">Nothing recorded for this term.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr><td colspan="2">Charged this term</td><td class="sa-num">{{ $n($termSum['charged']) }}</td><td></td></tr>
                        <tr><td colspan="2">Paid this term</td><td></td><td class="sa-num is-good">{{ $n($termSum['paid']) }}</td></tr>
                        <tr class="sa-total">
                            <td colspan="3">Balance carried forward</td>
                            <td @class(['sa-num', 'sa-big', 'is-bad' => $termSum['closing'] > 0, 'is-good' => $termSum['closing'] <= 0])>{{ $n($termSum['closing']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            @endif

            {{-- ══ LEDGER ══ --}}
            @if ($this->mode === 'ledger')
                <div class="sa-scroll">
                    <table class="sa-table">
                        <thead>
                            <tr>
                                <th>Date</th><th>Term</th><th>Details</th><th>Receipt / ref.</th>
                                <th class="sa-num">Debit</th><th class="sa-num">Credit</th><th class="sa-num">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($entries as $entry)
                                <tr @class(['sa-payment' => $entry['type'] === 'payment'])>
                                    <td class="sa-nowrap">{{ $date($entry['date']) }}</td>
                                    <td class="sa-muted">{{ $entry['term_name'] ?? '—' }}</td>
                                    <td>
                                        {{ $entry['description'] }}
                                        @if ($entry['discount'] > 0)<span class="sa-sub is-good">Less {{ $entry['discount_reason'] ?: 'discount' }}: {{ $n($entry['discount']) }}</span>@endif
                                    </td>
                                    <td class="sa-muted">{{ $entry['reference'] ?: '—' }}</td>
                                    <td class="sa-num">{{ $entry['debit'] > 0 ? $n($entry['debit']) : '' }}</td>
                                    <td class="sa-num is-good">{{ $entry['credit'] > 0 ? $n($entry['credit']) : '' }}</td>
                                    <td @class(['sa-num', 'sa-strong', 'is-bad' => $entry['balance'] > 0, 'is-good' => $entry['balance'] <= 0])>{{ $n($entry['balance']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="sa-none">Nothing on this account yet. Charges appear once the student has been billed.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </article>
    @endif

    <style>
        .sa-muted { color: #64748b; font-size: .82rem; }
        .sa-empty { text-align: center; padding: 3rem 1rem; border: 1px dashed #cbd5e1; border-radius: 12px; color: #475569; background: #fff; }
        .sa-empty a { color: #1a5fa8; font-weight: 600; }
        .sa-empty-icon { width: 2.5rem; height: 2.5rem; margin: 0 auto .5rem; color: #cbd5e1; }

        .sa-card { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; padding: 1.25rem 1.5rem; }
        .sa-profile { display: flex; align-items: center; gap: 1rem; }
        .sa-photo { width: 3.5rem; height: 3.5rem; border-radius: 999px; object-fit: cover; }
        .sa-who { flex: 1; min-width: 0; }
        .sa-name { font-size: 1.1rem; font-weight: 700; color: #16233a; }
        .sa-pill { font-size: .72rem; font-weight: 600; padding: .2rem .65rem; border-radius: 999px; }
        .sa-pill.is-good { background: #dcfce7; color: #166534; }
        .sa-pill.is-warn { background: #fef3c7; color: #92400e; }

        .sa-tiles { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; margin-top: 1.1rem; }
        @media (min-width: 768px) { .sa-tiles { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .sa-tile { background: #f8fafc; border-radius: 10px; padding: .75rem .9rem; }
        .sa-tile span { display: block; font-size: .7rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #64748b; }
        .sa-tile strong { display: block; font-size: 1.2rem; font-weight: 800; color: #16233a; font-variant-numeric: tabular-nums; margin-top: .15rem; }
        .sa-bar { height: 5px; background: #e2e8f0; border-radius: 999px; margin-top: .4rem; overflow: hidden; }
        .sa-bar div { height: 100%; background: #16a34a; }

        .is-good { color: #15803d !important; }
        .is-bad { color: #b91c1c !important; }

        .sa-paper { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; padding: 1.75rem 2rem; }
        .sa-doc-head { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; padding-bottom: 1rem; border-bottom: 3px double #1e3a5f; }
        .sa-doc-school { display: flex; gap: .85rem; align-items: center; }
        .sa-doc-school img { width: 3.25rem; height: 3.25rem; object-fit: contain; }
        .sa-doc-school h2 { font-size: 1.05rem; font-weight: 800; text-transform: uppercase; color: #1e3a5f; letter-spacing: .02em; }
        .sa-doc-school p, .sa-doc-title p { font-size: .78rem; color: #64748b; }
        .sa-doc-title { text-align: right; }
        .sa-doc-title h3 { font-size: .95rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: #16233a; }

        .sa-doc-student { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .6rem 1.5rem; margin: 1rem 0 1.25rem; }
        @media (min-width: 768px) { .sa-doc-student { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .sa-doc-student dt { font-size: .68rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #64748b; }
        .sa-doc-student dd { font-weight: 600; color: #16233a; }

        .sa-scroll { overflow-x: auto; }
        .sa-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .sa-table th { text-align: left; font-size: .7rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #64748b; padding: .55rem .7rem; border-bottom: 1.5px solid #1e3a5f; white-space: nowrap; }
        .sa-table td { padding: .6rem .7rem; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        .sa-table tfoot td { background: #f8fafc; font-weight: 600; }
        .sa-table tfoot tr.sa-total td { border-top: 1.5px solid #1e3a5f; }
        .sa-num { text-align: right !important; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .sa-big { font-size: 1.1rem; font-weight: 800; }
        .sa-strong { font-weight: 700; }
        .sa-nowrap { white-space: nowrap; }
        .sa-sub { display: block; font-size: .75rem; color: #64748b; }
        .sa-payment td { background: #f0fdf4; }
        .sa-bf td { background: #fffbeb; font-weight: 600; }
        .sa-none { text-align: center; color: #64748b; padding: 2rem !important; }
        .sa-note { margin-top: 1rem; font-size: .82rem; color: #475569; }

        @media print {
            @page { size: A4; margin: 14mm; }
            .sa-no-print, .fi-sidebar, .fi-topbar, .sh-topbar, .fi-header, .fi-footer { display: none !important; }
            .fi-main, .fi-page, .fi-main-ctn { padding: 0 !important; margin: 0 !important; }
            .sa-paper { border: none; padding: 0; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</x-filament-panels::page>
