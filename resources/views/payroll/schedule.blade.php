@extends('fees.layout')

@php
    $logo = $school?->logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($school->logo) : null;
    $n = fn ($v) => number_format((float) $v, 0);
@endphp

@section('title', "{$title} — {$period->period_label}")

@section('styles')
    @page { size: A4 landscape; margin: 10mm; }
    .sheet { max-width: 297mm; }
    .doc-title { display: flex; justify-content: space-between; align-items: flex-end; margin: 1rem 0; }
    .doc-title h2 { font-size: 1.05rem; text-transform: uppercase; letter-spacing: .1em; }
    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    th { text-align: left; font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; color: #4b5563; border-bottom: 1.5px solid #1e3a5f; padding: 6px 8px; }
    td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
    tbody tr:nth-child(even) td { background: #f8fafc; }
    tfoot td { font-weight: 700; border-top: 1.5px solid #1e3a5f; background: #f1f5fb; }
    .r { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .missing { color: #b91c1c; font-weight: 600; }
    .sign { display: flex; gap: 4rem; margin-top: 2.5rem; font-size: 12px; }
    .sign div { flex: 1; border-top: 1px solid #111827; padding-top: 4px; }
@endsection

@section('content')
    <div class="sheet">
        <div class="letterhead">
            @if ($logo)<img src="{{ $logo }}" alt="">@endif
            <div class="who">
                <h1>{{ $school?->name }}</h1>
                @if ($school?->address)<p>{{ $school->address }}</p>@endif
                <p>{{ collect([$school?->phone, $school?->email])->filter()->implode('  ·  ') }}</p>
            </div>
        </div>

        <div class="doc-title">
            <div>
                <h2>{{ $title }}</h2>
                <p class="muted">{{ $period->period_label }} · {{ \App\Models\PayrollPeriod::STATUSES[$period->status] ?? $period->status }}</p>
            </div>
            <p class="muted">Printed {{ now()->format('j M Y') }}</p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Staff no.</th>
                    <th>Name</th>
                    @if ($type === 'paye')
                        <th>TIN</th>
                        <th class="r">Gross pay</th>
                        <th class="r">Taxable pay</th>
                        <th class="r">PAYE</th>
                    @elseif ($type === 'lst')
                        <th>Position</th>
                        <th class="r">Gross pay</th>
                        <th class="r">LST this month</th>
                    @else
                        <th>Paid to</th>
                        <th>Account / number</th>
                        <th>Account name</th>
                        <th class="r">Net pay</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $i => $entry)
                    @php($staff = $entry->staff)
                    @php($bank = $staff?->primaryBankDetail)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $staff?->staff_no }}</td>
                        <td>{{ $staff?->name }}</td>
                        @if ($type === 'paye')
                            <td>@if ($staff?->tin_number){{ $staff->tin_number }}@else<span class="missing">No TIN</span>@endif</td>
                            <td class="r">{{ $n($entry->gross_pay) }}</td>
                            <td class="r">{{ $n($entry->taxable_income) }}</td>
                            <td class="r">{{ $n($entry->paye) }}</td>
                        @elseif ($type === 'lst')
                            <td>{{ $staff?->position }}</td>
                            <td class="r">{{ $n($entry->gross_pay) }}</td>
                            <td class="r">{{ $n($entry->lst) }}</td>
                        @else
                            @if (! $bank)
                                <td colspan="3"><span class="missing">No payment details on file</span></td>
                            @elseif ($bank->payment_method === 'mobile_money')
                                <td>{{ $bank->mobile_money_provider ?: 'Mobile money' }}</td>
                                <td>{{ $bank->mobile_money_number }}</td>
                                <td>{{ $bank->account_name ?: $staff?->name }}</td>
                            @else
                                <td>{{ collect([$bank->bank_name, $bank->branch])->filter()->implode(', ') ?: ucfirst((string) $bank->payment_method) }}</td>
                                <td>{{ $bank->account_number }}</td>
                                <td>{{ $bank->account_name }}</td>
                            @endif
                            <td class="r"><strong>{{ $n($entry->net_pay) }}</strong></td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted" style="text-align:center;padding:2rem">Nothing to list for this month.</td></tr>
                @endforelse
            </tbody>
            @if ($entries->isNotEmpty())
                <tfoot>
                    <tr>
                        @if ($type === 'paye')
                            <td colspan="4">Total ({{ $entries->count() }} staff)</td>
                            <td class="r">{{ $n($entries->sum('gross_pay')) }}</td>
                            <td class="r">{{ $n($entries->sum('taxable_income')) }}</td>
                            <td class="r">{{ $n($entries->sum('paye')) }}</td>
                        @elseif ($type === 'lst')
                            <td colspan="4">Total ({{ $entries->count() }} staff)</td>
                            <td class="r">{{ $n($entries->sum('gross_pay')) }}</td>
                            <td class="r">{{ $n($entries->sum('lst')) }}</td>
                        @else
                            <td colspan="6">Total ({{ $entries->count() }} staff)</td>
                            <td class="r">{{ $n($entries->sum('net_pay')) }}</td>
                        @endif
                    </tr>
                </tfoot>
            @endif
        </table>

        <div class="sign">
            <div>Prepared by (Bursar / Accountant)</div>
            <div>Approved by (Head teacher)</div>
            <div>Date</div>
        </div>
    </div>
@endsection
