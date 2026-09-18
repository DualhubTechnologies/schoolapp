{{--
    Student Account — ledger, statement, invoice.
    Save as: resources/views/filament/pages/student-account.blade.php

    All three read the same derived ledger, so they cannot disagree.
--}}

@php
    $student  = $this->student;
    $term     = $this->term;
    $entries  = $this->entries;
    $summary  = $this->summary;
    $termSum  = $this->termSummary;
    $school   = $student?->school;
    $currency = 'UGX';
@endphp

<x-filament-panels::page>

    {{-- ── Pickers (hidden when printing) ── --}}
    <div class="sh-no-print space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">Student</label>
                <select wire:model.live="studentId"
                        class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                    <option value="">Choose a student…</option>
                    @foreach ($this->studentOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Term (for the statement)</label>
                <select wire:model.live="termId"
                        class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                    @foreach ($this->termOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($student)
            <div class="flex flex-wrap items-center gap-2">
                @foreach ([
                    'ledger'    => ['Ledger', 'Every charge and payment'],
                    'statement' => ['Statement', 'One term, for the parent'],
                    'invoice'   => ['Invoice', 'What is owed right now'],
                ] as $key => [$label, $hint])
                    <button type="button"
                            wire:click="setMode('{{ $key }}')"
                            title="{{ $hint }}"
                            @class([
                                'rounded-lg px-4 py-2 text-sm font-medium transition',
                                'bg-primary-600 text-white' => $this->mode === $key,
                                'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300' => $this->mode !== $key,
                            ])>
                        {{ $label }}
                    </button>
                @endforeach

                <button type="button"
                        onclick="window.print()"
                        class="ml-auto rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">
                    Print
                </button>
            </div>
        @endif
    </div>

    @if (! $student)
        <div class="rounded-xl border border-dashed border-gray-300 p-10 text-center text-gray-500 dark:border-white/10">
            Choose a student to see their account.
        </div>
    @else

        {{-- ── Header, shown on every view and on print ── --}}
        <div class="mt-6 rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold">{{ $school?->name }}</h2>
                    @if ($school?->address)
                        <p class="text-sm text-gray-500">{{ $school->address }}</p>
                    @endif
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold uppercase tracking-wide">
                        @if ($this->mode === 'ledger') Student Ledger
                        @elseif ($this->mode === 'statement') Fee Statement
                        @else Invoice
                        @endif
                    </p>
                    <p class="text-xs text-gray-500">Printed {{ now()->format('d M Y') }}</p>
                    @if ($this->mode === 'statement' && $term)
                        <p class="text-xs text-gray-500">{{ $term->label() }}</p>
                    @endif
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-4 border-t border-gray-100 pt-4 text-sm dark:border-white/10 sm:grid-cols-4">
                <div>
                    <span class="block text-xs text-gray-500">Student</span>
                    <span class="font-medium">{{ $student->name }}</span>
                </div>
                <div>
                    <span class="block text-xs text-gray-500">Admission no.</span>
                    <span class="font-medium">{{ $student->admission_no }}</span>
                </div>
                <div>
                    <span class="block text-xs text-gray-500">Class</span>
                    <span class="font-medium">{{ $student->schoolClass?->name ?? '—' }}</span>
                </div>
                <div>
                    <span class="block text-xs text-gray-500">Stream</span>
                    <span class="font-medium">{{ $student->section?->name ?? '—' }}</span>
                </div>
            </div>
        </div>

        {{-- ══════════════ INVOICE ══════════════ --}}
        @if ($this->mode === 'invoice')
            <div class="mt-4 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10">
                    <h3 class="text-sm font-semibold">Amount due</h3>
                    <p class="text-xs text-gray-500">The position of this account today. Not a stored document — print it whenever you need a current one.</p>
                </div>

                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        <tr>
                            <td class="px-4 py-3">Total charged to date</td>
                            <td class="px-4 py-3 text-right font-medium">{{ $currency }} {{ number_format($summary['charged'], 0) }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3">Less payments received</td>
                            <td class="px-4 py-3 text-right font-medium text-green-700">− {{ $currency }} {{ number_format($summary['paid'], 0) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t-2 border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                        <tr>
                            <td class="px-4 py-4 text-right font-semibold">
                                {{ $summary['balance'] < 0 ? 'Credit on account' : 'Balance now due' }}
                            </td>
                            <td @class([
                                'px-4 py-4 text-right text-xl font-bold',
                                'text-red-600' => $summary['balance'] > 0,
                                'text-green-700' => $summary['balance'] <= 0,
                            ])>
                                {{ $currency }} {{ number_format(abs($summary['balance']), 0) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p class="mt-3 text-center text-xs text-gray-500">
                {{ $summary['percent'] }}% of the total owed has been paid.
            </p>
        @endif

        {{-- ══════════════ STATEMENT ══════════════ --}}
        @if ($this->mode === 'statement')
            <div class="mt-4 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Details</th>
                            <th class="px-4 py-3 text-right">Charge</th>
                            <th class="px-4 py-3 text-right">Paid</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        <tr class="bg-amber-50/50 dark:bg-amber-500/5">
                            <td class="px-4 py-3 text-gray-500">—</td>
                            <td class="px-4 py-3 font-medium">Balance brought forward</td>
                            <td class="px-4 py-3 text-right font-medium">
                                {{ $termSum['opening'] != 0 ? number_format($termSum['opening'], 0) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-400">—</td>
                        </tr>

                        @forelse ($entries as $entry)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ $entry['date'] ? \Illuminate\Support\Carbon::parse($entry['date'])->format('d M Y') : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ $entry['description'] }}
                                    @if ($entry['discount'] > 0)
                                        <span class="block text-xs text-green-600">
                                            Less {{ $entry['discount_reason'] ?: 'discount' }}: {{ number_format($entry['discount'], 0) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ $entry['debit'] > 0 ? number_format($entry['debit'], 0) : '' }}
                                </td>
                                <td class="px-4 py-3 text-right text-green-700">
                                    {{ $entry['credit'] > 0 ? number_format($entry['credit'], 0) : '' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-gray-500">
                                    Nothing recorded for this term.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="border-t-2 border-gray-200 bg-gray-50 text-sm dark:border-white/10 dark:bg-white/5">
                        <tr>
                            <td colspan="2" class="px-4 py-2 text-right text-gray-600">Charged this term</td>
                            <td class="px-4 py-2 text-right font-medium">{{ number_format($termSum['charged'], 0) }}</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="2" class="px-4 py-2 text-right text-gray-600">Paid this term</td>
                            <td></td>
                            <td class="px-4 py-2 text-right font-medium text-green-700">{{ number_format($termSum['paid'], 0) }}</td>
                        </tr>
                        <tr class="border-t border-gray-200 dark:border-white/10">
                            <td colspan="3" class="px-4 py-3 text-right font-semibold">Balance carried forward</td>
                            <td @class([
                                'px-4 py-3 text-right text-base font-bold',
                                'text-red-600' => $termSum['closing'] > 0,
                                'text-green-700' => $termSum['closing'] <= 0,
                            ])>
                                {{ number_format($termSum['closing'], 0) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        {{-- ══════════════ LEDGER ══════════════ --}}
        @if ($this->mode === 'ledger')
            <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Term</th>
                            <th class="px-4 py-3">Details</th>
                            <th class="px-4 py-3">Reference</th>
                            <th class="px-4 py-3 text-right">Debit</th>
                            <th class="px-4 py-3 text-right">Credit</th>
                            <th class="px-4 py-3 text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @forelse ($entries as $entry)
                            <tr @class(['bg-green-50/40 dark:bg-green-500/5' => $entry['type'] === 'payment'])>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ $entry['date'] ? \Illuminate\Support\Carbon::parse($entry['date'])->format('d M Y') : '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ $entry['term_name'] ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    {{ $entry['description'] }}
                                    @if ($entry['discount'] > 0)
                                        <span class="block text-xs text-green-600">
                                            Less {{ $entry['discount_reason'] ?: 'discount' }}: {{ number_format($entry['discount'], 0) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ $entry['reference'] ?: '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    {{ $entry['debit'] > 0 ? number_format($entry['debit'], 0) : '' }}
                                </td>
                                <td class="px-4 py-3 text-right text-green-700">
                                    {{ $entry['credit'] > 0 ? number_format($entry['credit'], 0) : '' }}
                                </td>
                                <td @class([
                                    'px-4 py-3 text-right font-medium',
                                    'text-red-600' => $entry['balance'] > 0,
                                    'text-green-700' => $entry['balance'] <= 0,
                                ])>
                                    {{ number_format($entry['balance'], 0) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-gray-500">
                                    Nothing on this account yet. Charges appear once the student has been billed.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        {{-- ── Account summary, on every view ── --}}
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                <span class="text-xs uppercase text-gray-500">Total charged</span>
                <p class="mt-1 text-xl font-bold">{{ $currency }} {{ number_format($summary['charged'], 0) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                <span class="text-xs uppercase text-gray-500">Total paid</span>
                <p class="mt-1 text-xl font-bold text-green-700">{{ $currency }} {{ number_format($summary['paid'], 0) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                <span class="text-xs uppercase text-gray-500">
                    {{ $summary['balance'] < 0 ? 'Credit on account' : 'Balance owing' }}
                </span>
                <p @class([
                    'mt-1 text-xl font-bold',
                    'text-red-600' => $summary['balance'] > 0,
                    'text-green-700' => $summary['balance'] <= 0,
                ])>
                    {{ $currency }} {{ number_format(abs($summary['balance']), 0) }}
                </p>
                <p class="mt-1 text-xs text-gray-500">{{ $summary['percent'] }}% paid</p>
            </div>
        </div>
    @endif

    @push('styles')
        <style>
            @media print {
                .sh-no-print,
                .fi-sidebar,
                .fi-topbar,
                .sh-topbar,
                .fi-footer { display: none !important; }

                .fi-main { padding: 0 !important; }
            }
        </style>
    @endpush
</x-filament-panels::page>
