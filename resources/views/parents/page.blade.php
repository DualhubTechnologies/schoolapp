@php
    $money = fn ($v) => 'UGX ' . number_format((float) $v, 0);
    $logo = $school?->logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($school->logo) : null;
    $owing = $balance > 0;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ $student->name }} · {{ $school->name }}</title>
    <link rel="icon" href="{{ $logo ?? asset('images/schoolhub-icon-192.png') }}">
    <style>
        :root { --ink: #0f1f38; --text: #334155; --muted: #64748b; --line: #e4e9f1; --blue: #1a5fa8; --bg: #f3f6fa; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: var(--bg); color: var(--text); line-height: 1.5; }
        .wrap { max-width: 40rem; margin: 0 auto; padding: 1rem 1rem 3rem; }
        header.school { display: flex; align-items: center; gap: .8rem; padding: .5rem 0 1rem; }
        header.school img { width: 3rem; height: 3rem; object-fit: contain; border-radius: .6rem; background: #fff; border: 1px solid var(--line); }
        header.school strong { display: block; color: var(--ink); font-size: 1.05rem; }
        header.school span { font-size: .8rem; color: var(--muted); }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 1rem; padding: 1.1rem 1.2rem; margin-bottom: 1rem; box-shadow: 0 1px 2px rgba(15, 31, 56, .04); }
        .card h2 { margin: 0 0 .75rem; font-size: .8rem; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); }
        .who { display: flex; align-items: center; gap: .9rem; }
        .who img { width: 3.5rem; height: 3.5rem; border-radius: 50%; object-fit: cover; background: #eef2f7; }
        .who strong { display: block; font-size: 1.15rem; color: var(--ink); }
        .who span { font-size: .85rem; color: var(--muted); }
        .balance { text-align: center; padding: 1.3rem 1rem; border-radius: 1rem; margin-bottom: 1rem; }
        .balance.owing { background: #fff7ed; border: 1px solid #fed7aa; }
        .balance.clear { background: #f0fdf4; border: 1px solid #bbf7d0; }
        .balance .label { font-size: .8rem; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); }
        .balance .figure { font-size: 2rem; font-weight: 800; color: var(--ink); margin: .2rem 0; }
        .balance.owing .figure { color: #c2410c; }
        .balance.clear .figure { color: #15803d; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; text-align: center; }
        .stats div { background: #f8fafc; border-radius: .7rem; padding: .6rem .3rem; }
        .stats dt { font-size: .7rem; color: var(--muted); }
        .stats dd { margin: 0; font-weight: 700; color: var(--ink); font-size: .92rem; }
        table { width: 100%; border-collapse: collapse; font-size: .88rem; }
        td { padding: .55rem 0; border-bottom: 1px solid #f0f3f8; vertical-align: top; }
        td.amt { text-align: right; white-space: nowrap; font-weight: 600; }
        td.amt.in { color: #15803d; }
        .small { font-size: .78rem; color: var(--muted); }
        a { color: var(--blue); font-weight: 600; text-decoration: none; }
        ul.list { list-style: none; margin: 0; padding: 0; }
        ul.list li { display: flex; justify-content: space-between; align-items: center; gap: .75rem; padding: .6rem 0; border-bottom: 1px solid #f0f3f8; }
        ul.list li:last-child, tr:last-child td { border-bottom: 0; }
        .btn { display: inline-block; padding: .45rem .85rem; border-radius: .6rem; background: #eff5fc; color: var(--blue); font-size: .85rem; }
        .pay p { margin: .2rem 0; }
        .schoolpay { margin-bottom: .75rem; padding: .8rem 1rem; border-radius: .8rem; background: #eff6ff; border: 1px dashed #93c5fd; text-align: center; }
        .schoolpay span { display: block; font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); }
        .schoolpay strong { display: block; font-size: 1.5rem; letter-spacing: .08em; color: var(--ink); user-select: all; }
        .schoolpay small { color: var(--muted); }
        footer { text-align: center; font-size: .75rem; color: var(--muted); margin-top: 1.5rem; }
        .back { display: inline-block; margin-bottom: .75rem; font-size: .9rem; font-weight: 600; color: #1d4ed8; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrap">
    @auth
        {{-- A parent signed in to SchoolHub came here from their home screen. --}}
        <a class="back" href="{{ route('filament.app.pages.dashboard') }}">&larr; Back to my SchoolHub</a>
    @endauth
    <header class="school">
        @if ($logo)<img src="{{ $logo }}" alt="">@endif
        <div>
            <strong>{{ $school->name }}</strong>
            <span>{{ collect([$school->phone, $school->email])->filter()->join(' · ') }}</span>
        </div>
    </header>

    <div class="card who">
        <img src="{{ $student->photoUrl() }}" alt="">
        <div>
            <strong>{{ $student->name }}</strong>
            <span>{{ $student->schoolClass?->name }}{{ $student->section ? ' ' . $student->section->name : '' }} · {{ $student->admission_no }}</span>
        </div>
    </div>

    <div class="balance {{ $owing ? 'owing' : 'clear' }}">
        <div class="label">{{ $balance < 0 ? 'In credit' : 'School fees balance' }}</div>
        <div class="figure">{{ $money(abs($balance)) }}</div>
        <div class="small">{{ $owing ? 'Still to pay' : ($balance < 0 ? 'Paid ahead' : 'Fully paid — thank you') }} · as at {{ now()->format('j M Y') }}</div>
    </div>

    @if ($termSummary)
        <div class="card">
            <h2>{{ $term->label() }}</h2>
            <dl class="stats">
                <div><dt>Brought forward</dt><dd>{{ number_format($termSummary['opening']) }}</dd></div>
                <div><dt>Billed</dt><dd>{{ number_format($termSummary['charged']) }}</dd></div>
                <div><dt>Paid</dt><dd>{{ number_format($termSummary['paid']) }}</dd></div>
            </dl>

            @if ($entries->isNotEmpty())
                <table style="margin-top: .8rem">
                    @foreach ($entries as $entry)
                        <tr>
                            <td>
                                {{ $entry['description'] }}
                                <div class="small">{{ \Illuminate\Support\Carbon::parse($entry['date'])->format('j M Y') }}</div>
                            </td>
                            <td class="amt {{ $entry['credit'] > 0 ? 'in' : '' }}">
                                {{ $entry['credit'] > 0 ? '−' . number_format($entry['credit']) : number_format($entry['debit']) }}
                            </td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>
    @endif

    @if ($payments->isNotEmpty())
        <div class="card">
            <h2>Receipts</h2>
            <ul class="list">
                @foreach ($payments as $payment)
                    <li>
                        <div>
                            <strong style="color: var(--ink)">{{ $money($payment->amount) }}</strong>
                            <div class="small">{{ $payment->paid_on?->format('j M Y') }} · {{ $payment->receipt_no }}</div>
                        </div>
                        <a class="btn" href="{{ route('parent.receipt', ['token' => $token, 'payment' => $payment->getKey()]) }}">View</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($reports->isNotEmpty())
        <div class="card">
            <h2>Report cards</h2>
            <ul class="list">
                @foreach ($reports as $reportTerm)
                    <li>
                        <span>{{ $reportTerm->label() }}</span>
                        <a class="btn" href="{{ route('parent.report', ['token' => $token, 'term' => $reportTerm->getKey()]) }}">Open</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($owing && ($student->schoolpay_code || $school->fee_payment_bank || $school->fee_payment_mobile_money || $school->fee_payment_instructions))
        <div class="card pay">
            <h2>How to pay</h2>
            @if ($student->schoolpay_code)
                <div class="schoolpay">
                    <span>SchoolPay code</span>
                    <strong>{{ $student->schoolpay_code }}</strong>
                    <small>Pay on mobile money (MTN or Airtel) or at a bank with this code.</small>
                </div>
            @endif
            @if ($school->fee_payment_bank)<p><strong>Bank:</strong> {{ $school->fee_payment_bank }}</p>@endif
            @if ($school->fee_payment_mobile_money)<p><strong>Mobile money:</strong> {{ $school->fee_payment_mobile_money }}</p>@endif
            @if ($school->fee_payment_instructions)<p>{!! nl2br(e($school->fee_payment_instructions)) !!}</p>@endif
            <p class="small">Always quote the admission number <strong>{{ $student->admission_no }}</strong>.</p>
        </div>
    @endif

    <footer>
        This page is private to {{ $student->name }}'s family — please do not share the link.<br>
        Questions about fees? Contact the school{{ $school->phone ? ' on ' . $school->phone : '' }}.<br>
        Powered by SchoolHub
    </footer>
</div>
</body>
</html>
