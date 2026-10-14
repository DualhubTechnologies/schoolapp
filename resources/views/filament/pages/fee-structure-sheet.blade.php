{{--
    Fees Structure — the sheet a school publishes (data from App\Services\FeeSheet).

    Styled with the scoped .fs-* rules at the bottom rather than Tailwind
    utilities, so it looks right without rebuilding the theme and prints
    cleanly on A4.
--}}

@php
    $school    = auth()->user()?->school;
    $term      = $this->term;
    $sheet     = $this->sheet;
    $columns   = $sheet?->columns() ?? [];
    $termly    = $sheet?->termly() ?? [];
    $admission = $sheet?->admission() ?? [];
    $firstTerm = $sheet?->firstTerm() ?? [];
    $other     = $sheet?->other() ?? [];
    $logo      = $school?->logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($school->logo) : null;
    $contacts  = collect([$school?->phone, $school?->email, $school?->website])->filter()->implode('  ·  ');
    $money     = fn ($v) => $v === null ? null : number_format((float) $v, 0);
    $notes     = collect(preg_split('/\R/', (string) $school?->fee_sheet_notes))->map(fn ($line) => trim($line))->filter()->values();
    $howToPay  = collect([
        'Bank' => $school?->fee_payment_bank,
        'Mobile money' => $school?->fee_payment_mobile_money,
        'SchoolPay' => $school?->schoolpay_enabled ? 'Pay with the learner\'s SchoolPay code, at the bank or on mobile money.' : null,
        'Note' => $school?->fee_payment_instructions,
    ])->filter();
@endphp

<x-filament-panels::page>

    {{-- ── Toolbar (not printed) ── --}}
    <div class="fs-toolbar fs-no-print">
        <div class="fs-toolbar-field">
            <label for="fs-term" class="fs-label">Term</label>
            <x-filament::input.wrapper>
                <x-filament::input.select id="fs-term" wire:model.live="termId">
                    @foreach ($this->termOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        <x-filament::button icon="heroicon-o-printer" onclick="window.print()">
            Print
        </x-filament::button>
    </div>

    @if (! $termly && ! $admission && ! $other)
        <div class="fs-empty">
            No fees are set up for {{ $term?->label() ?? 'this term' }} yet.
            Add them under <strong>Fees → Fee Setup</strong>.
        </div>
    @else

        <article class="fs-paper">

            {{-- ── Letterhead ── --}}
            <header class="fs-letterhead">
                @if ($logo)
                    <img src="{{ $logo }}" alt="" class="fs-logo">
                @endif
                <div>
                    <h1 class="fs-school">{{ $school?->name }}</h1>
                    @if ($school?->address)
                        <p class="fs-meta">{{ $school->address }}</p>
                    @endif
                    @if ($contacts)
                        <p class="fs-meta">{{ $contacts }}</p>
                    @endif
                    @if ($school?->motto)
                        <p class="fs-motto">“{{ $school->motto }}”</p>
                    @endif
                </div>
            </header>

            <div class="fs-title">
                <h2>Fees Structure</h2>
                <span class="fs-pill">{{ $term?->label() }}</span>
                @if ($term?->start_date && $term?->end_date)
                    <p class="fs-dates">Term begins {{ $term->start_date->format('l j F Y') }} and ends {{ $term->end_date->format('l j F Y') }}</p>
                @endif
            </div>

            {{-- ── Fees per term, one table per group of classes ── --}}
            @if ($termly)
                <div class="fs-subtitle">
                    <h2>Fees per term</h2>
                    <p>Paid every term by every learner.</p>
                </div>

                @foreach ($termly as $group)
                    @include('filament.pages.partials.fee-sheet-table', ['group' => $group, 'columns' => $columns, 'money' => $money, 'totalLabel' => 'Total per term'])
                @endforeach
            @endif

            {{-- ── On admission ── --}}
            @if ($admission)
                <div class="fs-subtitle">
                    <h2>On admission (new learners)</h2>
                    <p>Paid once, in a new learner's first term. Continuing learners do not pay these.</p>
                </div>

                @foreach ($admission as $group)
                    @include('filament.pages.partials.fee-sheet-table', ['group' => $group, 'columns' => $columns, 'money' => $money, 'totalLabel' => 'Total on admission'])
                @endforeach

                @if ($firstTerm)
                    <section class="fs-block">
                        <h3 class="fs-block-title">A new learner's first term in all</h3>
                        <table class="fs-table">
                            <thead>
                                <tr>
                                    <th>Classes</th>
                                    @foreach ($columns as $heading)
                                        <th class="fs-num">{{ $heading }} <span>(UGX)</span></th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($firstTerm as $row)
                                    <tr class="fs-strong">
                                        <td>{{ $row['classes'] }}</td>
                                        @foreach ($columns as $column => $heading)
                                            <td class="fs-num">{{ $money($row['totals'][$column] ?? 0) }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </section>
                @endif
            @endif

            {{-- ── Charged only where they apply ── --}}
            @if ($other)
                <div class="fs-subtitle">
                    <h2>Other charges</h2>
                    <p>Billed only to the learners they concern.</p>
                </div>

                <table class="fs-table fs-other">
                    <tbody>
                        @foreach ($other as $item)
                            <tr>
                                <td>{{ $item['name'] }}</td>
                                <td class="fs-detail">{{ $item['detail'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- ── How to pay, requirements and notes ── --}}
            <div @class(['fs-info', 'fs-info-2' => $notes->isNotEmpty()])>
                <section class="fs-box">
                    <h3 class="fs-block-title">How to pay</h3>
                    <dl class="fs-pay">
                        @foreach ($howToPay as $label => $value)
                            <dt>{{ $label }}</dt>
                            <dd>{{ $value }}</dd>
                        @endforeach
                    </dl>
                    <p class="fs-quote">Quote the learner's name and admission number on every payment, and keep your receipt.</p>
                </section>

                @if ($notes->isNotEmpty())
                    <section class="fs-box">
                        <h3 class="fs-block-title">Requirements and notes</h3>
                        <ul class="fs-notes">
                            @foreach ($notes as $note)
                                <li>{{ $note }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>

            <footer class="fs-footer">
                <div class="fs-sign">
                    <div><span></span>Bursar</div>
                    <div><span></span>Head teacher</div>
                </div>
                <p class="fs-issued">Issued {{ now()->format('j F Y') }}</p>
            </footer>
        </article>
    @endif

    <style>
        .fs-toolbar {
            display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
            max-width: 56rem; width: 100%; margin: 0 auto;
        }
        .fs-toolbar-field { min-width: 16rem; }
        .fs-label { display: block; margin-bottom: .35rem; font-size: .875rem; font-weight: 500; color: #374151; }

        .fs-empty {
            max-width: 56rem; margin: 0 auto; padding: 2.5rem 1.5rem; text-align: center;
            border: 1px dashed #cbd5e1; border-radius: 12px; color: #6b7280; font-size: .9rem;
        }
        .fs-empty-warn { border-color: #fcd34d; background: #fffbeb; color: #92400e; text-align: left; }

        .fs-paper {
            max-width: 56rem; width: 100%; margin: 0 auto; padding: 2.5rem 3rem;
            background: #fff; border: 1px solid #e5e7eb; border-radius: 14px;
            box-shadow: 0 1px 3px rgba(13, 31, 56, .06);
            color: #111827;
        }

        .fs-letterhead {
            display: flex; align-items: center; justify-content: center; gap: 1.25rem; text-align: center;
            padding-bottom: 1.25rem; border-bottom: 3px double #1e3a5f;
        }
        .fs-logo { height: 4.5rem; width: 4.5rem; object-fit: contain; }
        .fs-school { font-size: 1.5rem; font-weight: 800; letter-spacing: .02em; text-transform: uppercase; color: #1e3a5f; }
        .fs-meta { font-size: .85rem; color: #4b5563; margin-top: .15rem; }
        .fs-motto { font-size: .8rem; font-style: italic; color: #6b7280; margin-top: .35rem; }

        .fs-title { display: flex; flex-direction: column; align-items: center; gap: .5rem; margin: 1.75rem 0 1.5rem; }
        .fs-title h2 { font-size: 1.15rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        .fs-pill {
            display: inline-block; padding: .2rem .8rem; border-radius: 999px;
            background: #eff6ff; color: #1d4ed8; font-size: .8rem; font-weight: 600;
        }

        .fs-subtitle { margin: 2.25rem 0 1rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb; text-align: center; }
        .fs-subtitle h2 { font-size: 1rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
        .fs-subtitle p { font-size: .8rem; color: #6b7280; margin-top: .25rem; }

        .fs-block { margin-bottom: 1.5rem; break-inside: avoid; }
        .fs-block-title {
            font-size: .8rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
            color: #1e3a5f; margin-bottom: .5rem;
        }

        .fs-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .fs-table th {
            text-align: left; font-size: .72rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase;
            color: #6b7280; padding: .55rem .75rem; border-bottom: 1.5px solid #1e3a5f;
        }
        .fs-table th span { font-weight: 500; color: #9ca3af; }
        .fs-table td { padding: .6rem .75rem; border-bottom: 1px solid #f1f5f9; }
        .fs-table tbody tr:nth-child(even) td { background: #fafbfc; }
        .fs-table tfoot td {
            font-weight: 700; background: #f1f5fb; border-top: 1.5px solid #1e3a5f; border-bottom: none;
        }
        .fs-table tfoot td.fs-num { font-size: 1rem; color: #1e3a5f; }
        .fs-num { text-align: right !important; font-variant-numeric: tabular-nums; white-space: nowrap; width: 11rem; }

        .fs-dates { font-size: .82rem; color: #4b5563; }
        .fs-subtitle { text-align: left; }
        .fs-table tr.fs-strong td { font-weight: 600; }
        .fs-other td { vertical-align: top; }
        .fs-other td:first-child { font-weight: 600; width: 34%; }
        .fs-detail { color: #374151; }

        .fs-info { display: grid; gap: 1rem; margin-top: 2rem; break-inside: avoid; }
        .fs-info-2 { grid-template-columns: 1fr 1fr; }
        .fs-box { border: 1px solid #dbe3ee; border-radius: 10px; padding: .9rem 1rem; background: #f8fafc; }
        .fs-pay { display: grid; grid-template-columns: max-content 1fr; gap: .3rem .9rem; font-size: .85rem; }
        .fs-pay dt { font-weight: 600; color: #1e3a5f; }
        .fs-pay dd { color: #111827; white-space: pre-line; }
        .fs-quote { margin-top: .6rem; font-size: .78rem; color: #4b5563; }
        .fs-notes { list-style: disc; padding-left: 1.1rem; font-size: .85rem; display: grid; gap: .2rem; }

        .fs-footer { margin-top: 2.25rem; font-size: .8rem; color: #4b5563; break-inside: avoid; }
        .fs-sign { display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; }
        .fs-sign div { text-align: center; font-weight: 600; color: #1e3a5f; }
        .fs-sign span { display: block; height: 2.2rem; border-bottom: 1px dotted #6b7280; margin-bottom: .35rem; }
        .fs-issued { margin-top: 1rem; text-align: center; color: #9ca3af; }

        @media (max-width: 640px) {
            .fs-paper { padding: 1.5rem 1rem; }
            .fs-info-2 { grid-template-columns: 1fr; }
            .fs-table { font-size: .8rem; }
            .fs-table th, .fs-table td { padding: .5rem .4rem; }
            .fs-letterhead { flex-direction: column; }
            .fs-num { width: auto; }
        }

        @media print {
            @page { size: A4; margin: 14mm; }

            .fs-no-print,
            .fi-sidebar,
            .fi-topbar,
            .sh-topbar,
            .fi-header,
            .fi-footer { display: none !important; }

            .fi-main, .fi-page, .fi-main-ctn { padding: 0 !important; margin: 0 !important; }
            body, .fi-body { background: #fff !important; }

            .fs-paper { max-width: none; border: none; box-shadow: none; padding: 0; border-radius: 0; }
            .fs-table { font-size: .8rem; }
            .fs-table th, .fs-table td { padding: .32rem .6rem; }
            .fs-block { margin-bottom: .9rem; }
            .fs-subtitle { margin: 1.2rem 0 .6rem; padding-top: .8rem; }
            .fs-title { margin: 1rem 0 .8rem; }
            .fs-block-title, .fs-subtitle h2, .fs-subtitle p { padding-left: .6rem; }
            .fs-table tbody tr:nth-child(even) td,
            .fs-table tfoot td,
            .fs-box { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .fs-info-2 { grid-template-columns: 1fr 1fr; }
        }
    </style>
</x-filament-panels::page>
