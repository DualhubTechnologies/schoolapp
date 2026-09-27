@extends('fees.layout')

@php
    $logo = $school?->logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($school->logo) : null;

    try {
        $words = ucfirst(\Illuminate\Support\Number::spell((int) round((float) $payment->amount))) . ' shillings only';
    } catch (\Throwable $e) {
        $words = null;
    }
@endphp

@section('title', "Receipt {$payment->receipt_no}")

@section('styles')
    @page { size: A5 landscape; margin: 8mm; }
    .sheet { max-width: 210mm; }
    .receipt-head { display: flex; justify-content: space-between; align-items: flex-end; margin: 1rem 0 .9rem; }
    .receipt-head h2 { font-size: 1rem; letter-spacing: .14em; text-transform: uppercase; }
    .receipt-no { text-align: right; }
    .receipt-no .no { font-size: 1.15rem; font-weight: 800; color: #b91c1c; letter-spacing: .03em; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: .55rem 2rem; }
    .grid > div { border-bottom: 1px dotted #cbd5e1; padding-bottom: .3rem; }
    .amount-box { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin: 1rem 0; padding: .8rem 1rem; background: #f1f5fb; border-left: 4px solid #1e3a5f; }
    .amount-box .figure { font-size: 1.5rem; font-weight: 800; color: #1e3a5f; white-space: nowrap; }
    .amount-box .words { font-style: italic; color: #374151; }
    .foot { display: flex; justify-content: space-between; align-items: flex-end; gap: 2rem; margin-top: 1.4rem; }
    .sign { flex: 1; max-width: 16rem; border-top: 1px solid #111827; padding-top: .3rem; text-align: center; font-size: .75rem; color: #4b5563; }
    .balance { font-size: .9rem; }
    .split { display: flex; flex-wrap: wrap; gap: .35rem 1.5rem; align-items: baseline; margin: -.25rem 0 1rem; font-size: .9rem; }
    .void-stamp { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; }
    .void-stamp span { transform: rotate(-18deg); font-size: 5rem; font-weight: 900; letter-spacing: .15em; color: rgba(185, 28, 28, .18); border: 8px solid rgba(185, 28, 28, .18); padding: 0 1.5rem; }
    .void-note { margin-top: .8rem; color: #b91c1c; font-weight: 600; }
@endsection

@section('content')
    <div class="sheet">
        @if ($payment->isVoided())
            <div class="void-stamp"><span>VOID</span></div>
        @endif

        <div class="letterhead">
            @if ($logo)
                <img src="{{ $logo }}" alt="">
            @endif
            <div class="who">
                <h1>{{ $school?->name }}</h1>
                @if ($school?->address)<p>{{ $school->address }}</p>@endif
                <p>{{ collect([$school?->phone, $school?->email])->filter()->implode('  ·  ') }}</p>
            </div>
        </div>

        <div class="receipt-head">
            <div>
                <h2>Official receipt</h2>
                <p class="muted">School fees payment</p>
            </div>
            <div class="receipt-no">
                <div class="label">Receipt no.</div>
                <div class="no">{{ $payment->receipt_no }}</div>
                <div class="muted">{{ $payment->paid_on?->format('j M Y') }}</div>
            </div>
        </div>

        <div class="grid">
            <div><div class="label">Received from</div><div class="strong">{{ $payment->paid_by ?: ($student?->guardian?->name ?: '—') }}</div></div>
            <div><div class="label">Student</div><div class="strong">{{ $student?->name }}</div></div>
            <div><div class="label">Admission no.</div><div>{{ $student?->admission_no }}</div></div>
            <div><div class="label">Class</div><div>{{ $student?->schoolClass?->name }}{{ $student?->section ? ' · ' . $student->section->name : '' }}</div></div>
            <div><div class="label">Payment method</div><div>{{ $payment->methodLabel() }}</div></div>
            <div><div class="label">Transaction / slip ref.</div><div>{{ $payment->reference ?: '—' }}</div></div>
            @if ($payment->term)
                <div><div class="label">Term</div><div>{{ $payment->term->label() }}</div></div>
            @endif
        </div>

        <div class="amount-box">
            <div class="words">{{ $words }}</div>
            <div class="figure num">UGX {{ number_format((float) $payment->amount, 0) }}</div>
        </div>

        @if ($transportShare !== null)
            {{-- Van users: transport is paid first, the rest goes to school fees. --}}
            <div class="split">
                <span class="label">Applied to</span>
                <span>Transport (school van) <strong class="num">UGX {{ number_format($transportShare, 0) }}</strong></span>
                <span>School fees <strong class="num">UGX {{ number_format(max(0, (float) $payment->amount - $transportShare), 0) }}</strong></span>
            </div>
        @endif

        <div class="foot">
            <div class="balance">
                <span class="label">Balance after this payment</span><br>
                <span class="strong num">
                    @if ($balanceAfter > 0)
                        UGX {{ number_format($balanceAfter, 0) }}
                    @elseif ($balanceAfter < 0)
                        UGX {{ number_format(abs($balanceAfter), 0) }} in credit
                    @else
                        Fully paid
                    @endif
                </span>
            </div>
            <div class="sign">
                Received by: {{ $payment->recorded_by ?: '—' }}<br>Signature &amp; stamp
            </div>
        </div>

        @if ($payment->isVoided())
            <p class="void-note">Voided {{ $payment->voided_at->format('j M Y H:i') }} by {{ $payment->voided_by }} — {{ $payment->void_reason }}</p>
        @endif
    </div>
@endsection
