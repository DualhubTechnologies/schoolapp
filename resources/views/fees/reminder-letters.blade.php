@extends('fees.layout')

@php
    $logo = $school?->logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($school->logo) : null;
@endphp

@section('title', 'Fee reminder letters')

@section('styles')
    @page { size: A4; margin: 16mm; }
    .sheet { max-width: 210mm; min-height: 250mm; padding: 2.25rem 2.5rem; font-size: 14px; }
    .ref { display: flex; justify-content: space-between; margin: 1.5rem 0; }
    .subject { font-weight: 700; text-decoration: underline; margin: 1.25rem 0 1rem; text-transform: uppercase; letter-spacing: .03em; }
    .body p { margin-bottom: .85rem; }
    .owed { margin: 1rem 0 1.25rem; width: 100%; border-collapse: collapse; }
    .owed td { padding: .5rem .75rem; border: 1px solid #cbd5e1; }
    .owed td:last-child { text-align: right; font-weight: 700; }
    .sign { margin-top: 3rem; }
    .sign .line { width: 14rem; border-top: 1px solid #111827; margin-top: 2.5rem; padding-top: .3rem; }
@endsection

@section('content')
    @forelse ($letters as $letter)
        @php($student = $letter['student'])
        <div class="sheet">
            <div class="letterhead">
                @if ($logo)
                    <img src="{{ $logo }}" alt="">
                @endif
                <div class="who">
                    <h1>{{ $school?->name }}</h1>
                    @if ($school?->address)<p>{{ $school->address }}</p>@endif
                    <p>{{ collect([$school?->phone, $school?->email])->filter()->implode('  ·  ') }}</p>
                    @if ($school?->motto)<p><em>“{{ $school->motto }}”</em></p>@endif
                </div>
            </div>

            <div class="ref">
                <div>
                    <strong>{{ $student->guardian?->name ?: 'The Parent / Guardian' }}</strong><br>
                    Parent / Guardian of {{ $student->name }}<br>
                    {{ $student->schoolClass?->name }}{{ $student->section ? ' · ' . $student->section->name : '' }} — Adm. No. {{ $student->admission_no }}
                </div>
                <div>{{ now()->format('j F Y') }}</div>
            </div>

            <p>Dear Parent / Guardian,</p>

            <p class="subject">Re: Outstanding school fees{{ $letter['term'] ? ' — ' . $letter['term']->label() : '' }}</p>

            <div class="body">
                <p>
                    Our records show that the school fees account of your child,
                    <strong>{{ $student->name }}</strong>, has an outstanding balance as shown below.
                </p>

                <table class="owed">
                    <tr><td>Total fees charged to date</td><td class="num">UGX {{ number_format($student->totalCharged(), 0) }}</td></tr>
                    <tr><td>Total paid to date</td><td class="num">UGX {{ number_format($student->totalPaid(), 0) }}</td></tr>
                    <tr><td><strong>Balance outstanding</strong></td><td class="num">UGX {{ number_format($letter['balance'], 0) }}</td></tr>
                </table>

                <p>
                    You are kindly requested to clear this balance by <strong>{{ $letter['deadline'] }}</strong>.
                    @if ($student->schoolpay_code)
                        Payment can be made through <strong>SchoolPay using code {{ $student->schoolpay_code }}</strong>,
                        or at the bursar's office, quoting the student's admission number <strong>{{ $student->admission_no }}</strong>.
                    @else
                        Payment can be made at the bursar's office, by bank deposit or through SchoolPay / mobile money,
                        quoting the student's admission number <strong>{{ $student->admission_no }}</strong>.
                    @endif
                </p>

                <p>If you have already paid, please bring the receipt or bank slip to the bursar's office so that the account can be updated. Thank you for your continued support.</p>
            </div>

            <div class="sign">
                Yours faithfully,
                <div class="line">Bursar</div>
            </div>
        </div>
    @empty
        <div class="sheet"><p>None of the selected students has an outstanding balance.</p></div>
    @endforelse
@endsection
