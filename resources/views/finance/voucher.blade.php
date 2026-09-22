@extends('fees.layout')

@php
    $logo = $school?->logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($school->logo) : null;
    $expense = $entry->type === 'expense';

    try {
        $words = ucfirst(\Illuminate\Support\Number::spell((int) round((float) $entry->amount))) . ' shillings only';
    } catch (\Throwable $e) {
        $words = null;
    }
@endphp

@section('title', ($expense ? 'Payment voucher ' : 'Receipt voucher ') . $entry->voucher_no)

@section('styles')
    @page { size: A5 landscape; margin: 8mm; }
    .sheet { max-width: 210mm; }
    .head { display: flex; justify-content: space-between; align-items: flex-end; margin: 1rem 0 .9rem; }
    .head h2 { font-size: 1rem; letter-spacing: .14em; text-transform: uppercase; }
    .no { text-align: right; }
    .no .n { font-size: 1.15rem; font-weight: 800; color: #b91c1c; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: .55rem 2rem; }
    .grid > div { border-bottom: 1px dotted #cbd5e1; padding-bottom: .3rem; }
    .full { grid-column: 1 / -1; }
    .amount { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin: 1rem 0; padding: .8rem 1rem; background: #f1f5fb; border-left: 4px solid #1e3a5f; }
    .amount .figure { font-size: 1.45rem; font-weight: 800; color: #1e3a5f; white-space: nowrap; }
    .amount .words { font-style: italic; color: #374151; }
    .sigs { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-top: 2rem; }
    .sigs div { border-top: 1px solid #111827; padding-top: .3rem; text-align: center; font-size: .72rem; color: #4b5563; }
    .void-stamp { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; }
    .void-stamp span { transform: rotate(-18deg); font-size: 5rem; font-weight: 900; letter-spacing: .15em; color: rgba(185, 28, 28, .18); border: 8px solid rgba(185, 28, 28, .18); padding: 0 1.5rem; }
@endsection

@section('content')
    <div class="sheet">
        @if ($entry->isVoided())<div class="void-stamp"><span>VOID</span></div>@endif

        <div class="letterhead">
            @if ($logo)<img src="{{ $logo }}" alt="">@endif
            <div class="who">
                <h1>{{ $school?->name }}</h1>
                @if ($school?->address)<p>{{ $school->address }}</p>@endif
                <p>{{ collect([$school?->phone, $school?->email])->filter()->implode('  ·  ') }}</p>
            </div>
        </div>

        <div class="head">
            <div>
                <h2>{{ $expense ? 'Payment voucher' : 'Receipt voucher' }}</h2>
                <p class="muted">{{ $entry->term?->label() }}</p>
            </div>
            <div class="no">
                <div class="label">Voucher no.</div>
                <div class="n">{{ $entry->voucher_no }}</div>
                <div class="muted">{{ $entry->entry_date?->format('j M Y') }}</div>
            </div>
        </div>

        <div class="grid">
            <div><div class="label">{{ $expense ? 'Paid to' : 'Received from' }}</div><div class="strong">{{ $entry->party ?: '—' }}</div></div>
            <div><div class="label">Category</div><div>{{ $entry->category?->name }}</div></div>
            <div class="full"><div class="label">Being payment for</div><div>{{ $entry->description }}</div></div>
            <div><div class="label">Method</div><div>{{ \App\Models\FinanceEntry::METHODS[$entry->method] ?? $entry->method }}</div></div>
            <div><div class="label">Cheque / transaction / invoice no.</div><div>{{ $entry->reference ?: '—' }}</div></div>
        </div>

        <div class="amount">
            <div class="words">{{ $words }}</div>
            <div class="figure num">UGX {{ number_format((float) $entry->amount) }}</div>
        </div>

        <div class="sigs">
            <div>Prepared by: {{ $entry->recorded_by }}</div>
            <div>Approved by (Head teacher)</div>
            <div>{{ $expense ? 'Received by (payee)' : 'Received by (bursar)' }}</div>
        </div>

        @if ($entry->isVoided())
            <p style="margin-top:.8rem;color:#b91c1c;font-weight:600">Voided {{ $entry->voided_at->format('j M Y H:i') }} by {{ $entry->voided_by }} — {{ $entry->void_reason }}</p>
        @endif
    </div>
@endsection
