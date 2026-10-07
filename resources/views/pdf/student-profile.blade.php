<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Student Profile — {{ $student->name }}</title>
    {{-- Dompdf: tables for layout, no flex or grid. No "*" reset, which would also clear the page margin. --}}
    <style>
        @page { margin: 13mm 14mm 14mm; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #1f2937; line-height: 1.35; }
        table { border-collapse: collapse; }
        p, h1, h2 { margin: 0; }

        .head { width: 100%; }
        .head td { vertical-align: middle; }
        .logo { width: 62px; }
        .logo img { width: 58px; height: 58px; }
        .school { text-align: center; }
        .school-name { font-size: 17px; font-weight: bold; color: #1e3a5f; text-transform: uppercase; letter-spacing: .5px; }
        .school-meta { font-size: 9px; color: #4b5563; margin-top: 2px; }
        .motto { font-size: 9px; color: #4b5563; font-style: italic; margin-top: 2px; }
        .rule { border-bottom: 3px double #1e3a5f; margin: 8px 0 10px; }

        .title { text-align: center; margin-bottom: 12px; }
        .title span { display: inline-block; background: #1e3a5f; color: #fff; font-size: 11px; font-weight: bold; letter-spacing: 2px; padding: 4px 16px; border-bottom: 3px solid #d4a017; }
        .title p { font-size: 9px; color: #6b7280; margin-top: 4px; }

        .identity { width: 100%; border: 1px solid #d6dde8; margin-bottom: 12px; }
        .identity td { vertical-align: top; padding: 10px; }
        .photo { width: 106px; }
        .photo img { width: 100px; height: 118px; border: 1px solid #cbd5e1; }
        .photo .none { width: 100px; height: 118px; border: 1px solid #cbd5e1; background: #eef3fa; color: #1e3a5f; font-size: 34px; font-weight: bold; text-align: center; vertical-align: middle; }
        .name { font-size: 19px; font-weight: bold; color: #111827; }
        .reg { font-size: 11px; color: #1e3a5f; font-weight: bold; margin-top: 2px; }
        .badges { margin-top: 6px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 9px; font-size: 8px; font-weight: bold; text-transform: uppercase; margin-right: 4px; }
        .b-green { background: #d1fae5; color: #065f46; }
        .b-blue { background: #dbeafe; color: #1e40af; }
        .b-red { background: #fee2e2; color: #991b1b; }
        .b-amber { background: #fef3c7; color: #92400e; }
        .facts { width: 100%; margin-top: 8px; }
        .facts td { padding: 2px 10px 2px 0; width: 33%; vertical-align: top; }

        .label { display: block; font-size: 7.5px; font-weight: bold; color: #6b7280; text-transform: uppercase; letter-spacing: .4px; }
        .value { display: block; color: #111827; font-size: 10.5px; }

        .cols { width: 100%; margin-bottom: 10px; }
        .cols > tbody > tr > td { vertical-align: top; width: 50%; }
        .cols > tbody > tr > td.gap { width: 12px; }
        .card { border: 1px solid #d6dde8; }
        .card h2 { background: #eef3fa; color: #1e3a5f; font-size: 9px; text-transform: uppercase; letter-spacing: 1px; padding: 5px 9px; border-bottom: 1px solid #d6dde8; }
        .rows { width: 100%; }
        .rows td { padding: 4px 9px; border-bottom: 1px solid #eef1f5; vertical-align: top; }
        .rows tr:last-child td { border-bottom: 0; }
        .rows td.k { width: 42%; color: #6b7280; font-size: 9px; }
        .rows td.v { color: #111827; }

        .fees { width: 100%; }
        .fees td { width: 33%; text-align: center; padding: 8px 6px; }
        .fees .amount { font-size: 13px; font-weight: bold; margin-top: 2px; display: block; }
        .owing { color: #b91c1c; }
        .clear { color: #047857; }

        .note { padding: 8px 9px; font-size: 10px; }
        .note.medical { background: #fef2f2; color: #7f1d1d; }

        .sign { width: 100%; margin-top: 22px; }
        .sign td { width: 33%; padding: 0 10px; vertical-align: bottom; text-align: center; font-size: 9px; color: #374151; }
        .sign .line { border-top: 1px solid #111827; padding-top: 3px; }
        .sign img { height: 30px; }
        .stamp { width: 100%; border: 1px dashed #94a3b8; }
        .stamp td { height: 62px; color: #94a3b8; font-size: 8px; text-align: center; vertical-align: middle; padding: 0; }

        .footer { position: fixed; bottom: -6mm; left: 0; right: 0; text-align: center; font-size: 8px; color: #9ca3af; }
    </style>
</head>
<body>
@php
    $statusClass = match ($student->status) {
        'active' => 'b-green',
        'graduated' => 'b-blue',
        'withdrawn' => 'b-red',
        default => 'b-amber',
    };
    $initials = collect(preg_split('/\s+/', trim($student->name)))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
    $dash = '—';
    $guardian = $student->guardian;
    $subjects = $student->electives->pluck('name')->implode(', ');
@endphp

    <div class="footer">{{ $school->name }} · Student profile of {{ $student->name }} ({{ $student->admission_no }}) · Printed {{ now()->format('j M Y, g:i a') }} · Confidential</div>

    <table class="head">
        <tr>
            <td class="logo">@if ($logoPath)<img src="{{ $logoPath }}" alt="">@endif</td>
            <td class="school">
                <div class="school-name">{{ $school->name }}</div>
                <div class="school-meta">{{ collect([$school->address, $school->phone ? 'Tel: '.$school->phone : null, $school->email])->filter()->implode('  ·  ') }}</div>
                @if ($school->motto)<div class="motto">“{{ $school->motto }}”</div>@endif
            </td>
            <td class="logo"></td>
        </tr>
    </table>
    <div class="rule"></div>

    <div class="title">
        <span>STUDENT PROFILE</span>
        @if ($term)<p>{{ $term->label() }}</p>@endif
    </div>

    {{-- Who --}}
    <table class="identity">
        <tr>
            <td class="photo">
                @if ($photoPath)
                    <img src="{{ $photoPath }}" alt="">
                @else
                    <table><tr><td class="none">{{ $initials }}</td></tr></table>
                @endif
            </td>
            <td>
                <div class="name">{{ $student->name }}</div>
                <div class="reg">Reg. No. {{ $student->admission_no }}</div>
                <div class="badges">
                    <span class="badge {{ $statusClass }}">{{ \App\Models\Student::STATUSES[$student->status] ?? $student->status }}</span>
                    @if ($student->enrolment_status)
                        <span class="badge {{ $student->enrolment_status === 'confirmed' ? 'b-green' : 'b-amber' }}">{{ \App\Models\Student::ENROLMENT_STATUSES[$student->enrolment_status] ?? $student->enrolment_status }}</span>
                    @endif
                </div>
                <table class="facts">
                    <tr>
                        <td><span class="label">Class</span><span class="value">{{ $student->schoolClass?->name ?? $dash }}{{ $student->section ? ' · '.$student->section->name : '' }}</span></td>
                        <td><span class="label">Residency</span><span class="value">{{ $student->residencyType?->name ?? $dash }}</span></td>
                        <td><span class="label">House</span><span class="value">{{ $student->house?->name ?? $dash }}</span></td>
                    </tr>
                    <tr>
                        <td><span class="label">Sex</span><span class="value">{{ \App\Models\Student::GENDERS[$student->gender] ?? $dash }}</span></td>
                        <td><span class="label">Age</span><span class="value">{{ $student->age !== null ? $student->age.' years' : $dash }}</span></td>
                        <td><span class="label">Admitted</span><span class="value">{{ $student->admission_date?->format('j M Y') ?? $dash }}</span></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Personal and admission --}}
    <table class="cols">
        <tr>
            <td>
                <div class="card">
                    <h2>Personal information</h2>
                    <table class="rows">
                        <tr><td class="k">Full name</td><td class="v">{{ $student->name }}</td></tr>
                        <tr><td class="k">Date of birth</td><td class="v">{{ $student->date_of_birth?->format('j F Y') ?? $dash }}</td></tr>
                        <tr><td class="k">Sex</td><td class="v">{{ \App\Models\Student::GENDERS[$student->gender] ?? $dash }}</td></tr>
                        <tr><td class="k">National ID (NIN)</td><td class="v">{{ $student->nin ?: $dash }}</td></tr>
                        <tr><td class="k">Home address</td><td class="v">{{ $student->address ?: $dash }}</td></tr>
                    </table>
                </div>
            </td>
            <td class="gap"></td>
            <td>
                <div class="card">
                    <h2>Admission and identification</h2>
                    <table class="rows">
                        <tr><td class="k">Registration no.</td><td class="v">{{ $student->admission_no }}</td></tr>
                        <tr><td class="k">Admission date</td><td class="v">{{ $student->admission_date?->format('j F Y') ?? $dash }}</td></tr>
                        <tr><td class="k">LIN</td><td class="v">{{ $student->lin ?: $dash }}</td></tr>
                        @if ($student->schoolpay_code)<tr><td class="k">SchoolPay code</td><td class="v">{{ $student->schoolpay_code }}</td></tr>@endif
                        <tr><td class="k">Enrolment</td><td class="v">{{ $student->confirmed_at ? 'Confirmed '.$student->confirmed_at->format('j M Y') : 'Not yet confirmed' }}</td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- Parent and academic --}}
    <table class="cols">
        <tr>
            <td>
                <div class="card">
                    <h2>Parent / guardian</h2>
                    <table class="rows">
                        <tr><td class="k">Name</td><td class="v">{{ $guardian?->name ?? $dash }}</td></tr>
                        <tr><td class="k">Relationship</td><td class="v">{{ $guardian ? (\App\Models\Guardian::RELATIONSHIPS[$guardian->relationship] ?? ucfirst((string) $guardian->relationship)) : $dash }}</td></tr>
                        <tr><td class="k">Phone</td><td class="v">{{ $guardian?->phone ?: $dash }}</td></tr>
                        <tr><td class="k">Email</td><td class="v">{{ $guardian?->email ?: $dash }}</td></tr>
                        <tr><td class="k">Learner's phone</td><td class="v">{{ $student->phone ?: $dash }}</td></tr>
                    </table>
                </div>
            </td>
            <td class="gap"></td>
            <td>
                <div class="card">
                    <h2>Class and studies</h2>
                    <table class="rows">
                        <tr><td class="k">Class</td><td class="v">{{ $student->schoolClass?->name ?? $dash }}</td></tr>
                        <tr><td class="k">Stream</td><td class="v">{{ $student->section?->name ?? $dash }}</td></tr>
                        @if ($student->combination)<tr><td class="k">Combination</td><td class="v">{{ $student->combination->name }}</td></tr>@endif
                        <tr><td class="k">{{ $student->combination ? 'Subsidiary' : 'Elective subjects' }}</td><td class="v">{{ $subjects ?: $dash }}</td></tr>
                        <tr><td class="k">Residency</td><td class="v">{{ $student->residencyType?->name ?? $dash }}</td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- Fees --}}
    <div class="card" style="margin-bottom: 10px">
        <h2>Fees position{{ $term ? ' — '.$term->label() : '' }}</h2>
        <table class="fees">
            <tr>
                <td><span class="label">Total paid</span><span class="amount">UGX {{ number_format($totalPaid) }}</span></td>
                <td><span class="label">{{ $balance > 0 ? 'Balance due' : ($balance < 0 ? 'In credit' : 'Balance') }}</span><span class="amount {{ $balance > 0 ? 'owing' : 'clear' }}">{{ $balance == 0 ? 'Cleared' : 'UGX '.number_format(abs($balance)) }}</span></td>
                <td><span class="label">As at</span><span class="amount" style="font-size: 11px">{{ now()->format('j M Y') }}</span></td>
            </tr>
        </table>
    </div>

    {{-- Health --}}
    <div class="card">
        <h2>Medical and emergency notes</h2>
        <div class="note {{ $student->medical_notes ? 'medical' : '' }}">{{ $student->medical_notes ?: 'None recorded.' }}</div>
    </div>

    {{-- Office use --}}
    <table class="sign">
        <tr>
            <td>
                <div class="line">Class teacher</div>
            </td>
            <td>
                @if ($signaturePath)<img src="{{ $signaturePath }}" alt=""><br>@endif
                <div class="line">Head teacher</div>
            </td>
            <td>
                <table class="stamp"><tr><td>School stamp</td></tr></table>
            </td>
        </tr>
    </table>
</body>
</html>
