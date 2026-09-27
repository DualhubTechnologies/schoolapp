{{--
    Letter of admission (A4, rendered by Dompdf: tables, no flexbox/grid).
    Addressed to the parent, with a subject line, the learner's details,
    this term's fees and how to pay, the head teacher's signature and a
    tear-off acceptance slip for the parent to return.
--}}
@php
    use Illuminate\Support\Str;

    $name = Str::title(Str::lower((string) $student->name));
    $guardian = $student->guardian;
    $guardianName = $guardian?->name ? Str::title(Str::lower($guardian->name)) : null;
    $class = $student->schoolClass?->name ?? '—';
    $stream = $student->section?->name;
    $termLabel = $term?->label();
    $confirmed = $student->isConfirmed();
    $money = fn ($value) => 'UGX ' . number_format((float) $value, 0);

    // Reporting: the opening day while it is still ahead; once the term has
    // started, the learner reports straight away.
    $opens = $term?->start_date;
    $reportText = $opens && $opens->isFuture()
        ? 'The term opens on <strong>' . e($opens->format('l, j F Y')) . '</strong>. Please report on or before that day.'
        : ($termLabel
            ? e($termLabel) . ' is already in session. Please report to the school office as soon as possible.'
            : 'The reporting date will be communicated by the school.');

    $contact = collect([
        $school->address ?: null,
        $school->city,
        $school->phone ? 'Tel: ' . $school->phone : null,
        $school->email,
        $school->website,
    ])->filter()->implode('  ·  ');

    $ref = 'ADM/' . preg_replace('/^ADM[-\/ ]*/i', '', (string) $student->admission_no) . '/' . ($term?->academicYear?->name ?? now()->format('Y'));
    $hasPaymentDetails = $student->schoolpay_code || $school->fee_payment_bank || $school->fee_payment_mobile_money || $school->fee_payment_instructions;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Letter of Admission — {{ $name }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { margin: 12mm 18mm 11mm 18mm; font-family: 'DejaVu Sans', sans-serif; font-size: 9.4px; color: #1f2937; line-height: 1.42; }
        table { border-collapse: collapse; width: 100%; }
        p { margin: 0 0 6px; }
        strong { color: #0f1f38; }

        .watermark { position: fixed; top: 290px; left: 170px; width: 340px; opacity: .05; }

        /* Letterhead */
        .letterhead td { vertical-align: middle; }
        .logo { width: 62px; height: 62px; }
        .school { text-align: center; }
        .school-name { font-family: 'DejaVu Serif', serif; font-size: 19px; font-weight: bold; letter-spacing: 1px; color: #0f2c5c; text-transform: uppercase; }
        .motto { font-family: 'DejaVu Serif', serif; font-style: italic; font-size: 9.5px; color: #8a6d1d; margin-top: 2px; }
        .contact { font-size: 8px; color: #4b5563; margin-top: 4px; }
        .rule-thick { border-top: 2.5px solid #0f2c5c; margin-top: 8px; }
        .rule-thin { border-top: 0.8px solid #c9a227; margin-top: 2px; }

        .meta { margin: 9px 0 8px; font-size: 9px; }
        .meta td { padding: 0; }
        .meta .label { color: #6b7280; }

        .to { margin-bottom: 7px; line-height: 1.45; }
        .subject { margin: 3px 0 8px; font-weight: bold; font-size: 10px; color: #0f2c5c; text-transform: uppercase; text-decoration: underline; letter-spacing: .2px; }

        /* Learner details */
        .details { margin: 4px 0 10px; border: 0.8px solid #d6dde8; }
        .details td { padding: 3.5px 8px; border-bottom: 0.8px solid #e6ebf2; font-size: 9.2px; }
        .details .k { width: 20%; color: #6b7280; background: #f6f8fb; }
        .details .v { width: 30%; font-weight: bold; color: #0f1f38; }
        .status-ok { color: #15803d; }
        .status-wait { color: #b45309; }

        h3 { margin: 9px 0 4px; font-size: 9px; letter-spacing: 1.2px; text-transform: uppercase; color: #0f2c5c; }

        /* Fees */
        .fees td { padding: 3px 8px; border-bottom: 0.8px solid #eef1f6; }
        .fees .amt { text-align: right; white-space: nowrap; }
        .fees thead td { font-size: 8px; text-transform: uppercase; letter-spacing: .8px; color: #6b7280; border-bottom: 1px solid #0f2c5c; }
        .fees .total td { border-top: 1.2px solid #0f2c5c; border-bottom: 0; font-weight: bold; font-size: 10.5px; color: #0f2c5c; padding-top: 6px; }

        .pay { margin-top: 6px; padding: 5px 9px; background: #f6f8fb; border-left: 2.5px solid #c9a227; font-size: 9px; }
        .pay td { padding: 1px 0; vertical-align: top; }
        .pay .k { width: 26%; color: #6b7280; }

        ul { margin: 0 0 6px 14px; padding: 0; }
        li { margin-bottom: 2px; }

        /* Signature */
        .sign { margin-top: 8px; }
        .sign td { vertical-align: bottom; }
        .sig-img { height: 34px; }
        .sig-line { border-top: 0.8px solid #374151; width: 190px; padding-top: 3px; font-size: 9px; }
        .stamp { width: 110px; height: 54px; border: 0.8px dashed #9ca3af; text-align: center; vertical-align: middle; color: #9ca3af; font-size: 8px; }

        /* Tear-off slip */
        .cut { margin: 10px 0 5px; border-top: 1px dashed #9ca3af; text-align: center; font-size: 7.5px; color: #9ca3af; }
        .slip-title { font-weight: bold; font-size: 9.5px; color: #0f2c5c; text-transform: uppercase; letter-spacing: .6px; margin-bottom: 4px; }
        .slip td { padding: 4px 0 0; font-size: 9px; }
        .blank { border-bottom: 0.8px dotted #6b7280; }

        .footer { position: fixed; bottom: 4mm; left: 0; right: 0; text-align: center; font-size: 7px; color: #9ca3af; }
    </style>
</head>
<body>

@if ($logoPath)
    <img class="watermark" src="{{ $logoPath }}" alt="">
@endif

{{-- Letterhead --}}
<table class="letterhead">
    <tr>
        <td style="width: 80px;">
            @if ($logoPath)<img class="logo" src="{{ $logoPath }}" alt="">@endif
        </td>
        <td class="school">
            <div class="school-name">{{ $school->name }}</div>
            @if ($school->motto)<div class="motto">“{{ $school->motto }}”</div>@endif
            @if ($contact)<div class="contact">{{ $contact }}</div>@endif
        </td>
        <td style="width: 80px;"></td>
    </tr>
</table>
<div class="rule-thick"></div>
<div class="rule-thin"></div>

<table class="meta">
    <tr>
        <td><span class="label">Our ref:</span> <strong>{{ $ref }}</strong></td>
        <td style="text-align: right;"><span class="label">Date:</span> <strong>{{ now()->format('j F Y') }}</strong></td>
    </tr>
</table>

<div class="to">
    The Parent / Guardian of <strong>{{ $name }}</strong><br>
    @if ($guardianName){{ $guardianName }}@if ($guardian?->phone) &nbsp;·&nbsp; {{ $guardian->phone }}@endif<br>@endif
</div>

<p>Dear {{ $guardianName ?: 'Parent / Guardian' }},</p>

<div class="subject">Re: Offer of admission — {{ $name }}, {{ $class }}{{ $termLabel ? ', ' . $termLabel : '' }}</div>

<p>
    We are pleased to offer your child, <strong>{{ $name }}</strong>, a place in <strong>{{ $class }}{{ $stream ? ' ' . $stream : '' }}</strong>
    at {{ $school->name }}{{ $termLabel ? ', beginning ' . $termLabel : '' }}.
    Please keep this letter safe; it is your child's record of admission.
</p>

<table class="details">
    <tr>
        <td class="k">Learner</td><td class="v">{{ $name }}</td>
        <td class="k">Admission no.</td><td class="v">{{ $student->admission_no }}</td>
    </tr>
    <tr>
        <td class="k">Class</td><td class="v">{{ $class }}{{ $stream ? ' — ' . $stream : '' }}</td>
        <td class="k">Residency</td><td class="v">{{ $student->residencyType?->name ?? '—' }}</td>
    </tr>
    <tr>
        <td class="k">Date admitted</td><td class="v">{{ ($student->admission_date ?? $student->created_at)?->format('j M Y') }}</td>
        <td class="k">Admission</td>
        <td class="v {{ $confirmed ? 'status-ok' : 'status-wait' }}">{{ $confirmed ? 'Confirmed' : 'Provisional' }}</td>
    </tr>
    @if ($student->lin || $student->schoolpay_code)
        <tr>
            <td class="k">LIN</td><td class="v">{{ $student->lin ?: '—' }}</td>
            <td class="k">SchoolPay code</td><td class="v">{{ $student->schoolpay_code ?: '—' }}</td>
        </tr>
    @endif
</table>

@if ($feeLines->isNotEmpty())
    <h3>Fees for {{ $termLabel ?? 'this term' }}</h3>
    <table class="fees">
        <thead><tr><td>Item</td><td class="amt">Amount</td></tr></thead>
        <tbody>
            @foreach ($feeLines as $fee)
                <tr>
                    <td>{{ $fee->name }}</td>
                    <td class="amt">{{ $money($fee->amount) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td>Total payable</td>
                <td class="amt">{{ $money($feeTotal) }}</td>
            </tr>
        </tbody>
    </table>
@endif

@if ($hasPaymentDetails)
    <div class="pay">
        <table>
            @if ($student->schoolpay_code)
                <tr><td class="k">SchoolPay</td><td>Pay with code <strong>{{ $student->schoolpay_code }}</strong> (mobile money or bank)</td></tr>
            @endif
            @if ($school->fee_payment_bank)
                <tr><td class="k">Bank</td><td>{{ $school->fee_payment_bank }}</td></tr>
            @endif
            @if ($school->fee_payment_mobile_money)
                <tr><td class="k">Mobile money</td><td>{{ $school->fee_payment_mobile_money }}</td></tr>
            @endif
            @if ($school->fee_payment_instructions)
                <tr><td class="k">Note</td><td>{{ $school->fee_payment_instructions }}</td></tr>
            @endif
            <tr><td class="k">Reference</td><td>Always quote admission number <strong>{{ $student->admission_no }}</strong>.</td></tr>
        </table>
    </div>
@endif

<h3>Reporting</h3>
<p>{!! $reportText !!}</p>
<ul>
    <li>Bring this letter and your child's most recent report card or school records.</li>
    @unless ($student->lin)
        <li>Bring your child's Learner Identification Number (LIN), if they have one.</li>
    @endunless
    @unless ($confirmed)
        <li>The place is confirmed once the first fees payment is received by the school.</li>
    @endunless
    <li>Learners are expected to follow the school's rules and regulations.</li>
</ul>

<p>Congratulations, and welcome to the {{ $school->name }} family.</p>

<table class="sign">
    <tr>
        <td>
            <p style="margin-bottom: 2px;">Yours faithfully,</p>
            @if ($signaturePath)
                <img class="sig-img" src="{{ $signaturePath }}" alt="">
            @else
                <div style="height: 28px;"></div>
            @endif
            <div class="sig-line"><strong>Head Teacher</strong><br>{{ $school->name }}</div>
        </td>
        <td style="width: 120px; text-align: right;">
            <table style="width: 110px; margin-left: auto;"><tr><td class="stamp">School stamp</td></tr></table>
        </td>
    </tr>
</table>

{{-- Acceptance slip, kept in one piece --}}
<div style="page-break-inside: avoid;">
<div class="cut">✂ &nbsp; cut here and return this part to the school &nbsp; ✂</div>
<div class="slip-title">Acceptance of admission</div>
<p style="margin-bottom: 2px;">
    I accept the offer of admission for <strong>{{ $name }}</strong> (Adm. no. {{ $student->admission_no }})
    to {{ $class }}{{ $termLabel ? ', ' . $termLabel : '' }}, and agree to the conditions above.
</p>
<table class="slip">
    <tr>
        <td style="width: 24%;">Parent / guardian's name:</td>
        <td class="blank" style="width: 40%;">{{ $guardianName }}</td>
        <td style="width: 10%; padding-left: 10px;">Phone:</td>
        <td class="blank">{{ $guardian?->phone }}</td>
    </tr>
    <tr>
        <td>Signature:</td>
        <td class="blank">&nbsp;</td>
        <td style="padding-left: 10px;">Date:</td>
        <td class="blank">&nbsp;</td>
    </tr>
</table>
</div>

<div class="footer">{{ $school->name }} · Letter of admission {{ $ref }} · Printed {{ now()->format('j M Y') }}</div>

</body>
</html>
