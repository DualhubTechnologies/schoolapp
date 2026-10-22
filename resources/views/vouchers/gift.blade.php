{{--
    Gift vouchers: four to an A4 page, cut along the dashed lines.
    $codes: SubscriptionActivationCode (with plan). $contact: config('contact').
--}}
@php
    $site = preg_replace('#^https?://(www\.)?#', '', rtrim((string) config('app.url'), '/'));
    $period = fn ($code) => match ($code->cycle) {
        'year' => ['1 year', 'free'],
        'term' => ['1 term', 'free'],
        default => ['Free', 'SchoolHub'],
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SchoolHub gift vouchers</title>
    <style>
        @page { size: A4; margin: 8mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Segoe UI", Roboto, Arial, sans-serif; color: #16233a; background: #eef1f6; }
        .toolbar { position: sticky; top: 0; z-index: 5; display: flex; justify-content: center; align-items: center; gap: .6rem; padding: .7rem; background: #0d1f38; color: #cbd5e1; font-size: .85rem; }
        .toolbar button { font: inherit; font-weight: 600; padding: .5rem 1rem; border-radius: 8px; border: 0; cursor: pointer; }
        .toolbar .primary { background: #2472c4; color: #fff; }
        .toolbar .ghost { background: rgba(255,255,255,.1); color: #fff; }

        .page { width: 194mm; height: 281mm; margin: 6mm auto; background: #fff; display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; }
        .cut { padding: 4mm; border: 1px dashed #b6bfcc; margin: -.5px; }
        .v { height: 100%; border-radius: 4mm; overflow: hidden; display: flex; flex-direction: column; background: #fff; box-shadow: 0 0 0 .4mm #dfe5ee; }

        .v-top { position: relative; background: linear-gradient(150deg, #24357a 0%, #1e2a5e 55%, #141d45 100%); color: #fff; padding: 6mm 6mm 5mm; }
        .v-top::after { content: ""; position: absolute; inset: 0; background: repeating-linear-gradient(135deg, rgba(255,255,255,.05) 0 1px, transparent 1px 9px); }
        .brand { position: relative; z-index: 1; display: flex; align-items: center; gap: 2.2mm; font-weight: 800; font-size: 15pt; letter-spacing: -.01em; }
        .brand img { width: 9mm; height: 9mm; }
        .brand em { font-style: normal; color: #f59e0b; }
        .ribbon { position: absolute; z-index: 1; top: 6.5mm; right: 0; background: #f39c12; color: #1e2a5e; font-weight: 800; font-size: 8pt; letter-spacing: .18em; padding: 1.6mm 4mm 1.6mm 5mm; border-radius: 3mm 0 0 3mm; }
        .gift { position: relative; z-index: 1; margin-top: 5mm; font-weight: 800; line-height: .95; }
        .gift b { display: block; font-size: 30pt; letter-spacing: -.03em; color: #ffc65c; }
        .gift small { display: block; margin-top: 1.6mm; font-size: 10pt; font-weight: 600; color: #d6deef; }

        .v-body { flex: 1; display: flex; flex-direction: column; padding: 4.5mm 6mm 4mm; }
        .incl { font-size: 8.6pt; color: #46556d; line-height: 1.45; }
        .incl b { color: #1e2a5e; }
        .code { margin-top: 3.5mm; border: .5mm dashed #f39c12; background: #fff8ec; border-radius: 2.5mm; padding: 2.4mm 3mm; text-align: center; }
        .code small { display: block; font-size: 7pt; font-weight: 700; letter-spacing: .16em; color: #9a6206; }
        .code strong { display: block; margin-top: .6mm; font-family: ui-monospace, Consolas, "Courier New", monospace; font-size: 15pt; letter-spacing: .06em; color: #1e2a5e; }
        .steps { margin-top: 3.5mm; padding-left: 4.2mm; font-size: 8.4pt; line-height: 1.5; color: #2b3a52; }
        .steps b { color: #1e2a5e; }
        .note { margin-top: 2.4mm; font-size: 7.6pt; color: #5b6b82; line-height: 1.4; }
        .v-foot { margin-top: auto; padding-top: 2.6mm; border-top: 1px solid #edf0f5; display: flex; justify-content: space-between; align-items: flex-end; gap: 2mm; font-size: 8pt; color: #46556d; }
        .v-foot b { display: block; font-size: 10.5pt; color: #1e2a5e; }
        .valid { text-align: right; }
        .valid b { color: #b45309; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { margin: 0; page-break-after: always; }
            .page:last-child { page-break-after: auto; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <span>{{ $codes->count() }} {{ str('voucher')->plural($codes->count()) }}, 4 to a page</span>
        <button class="primary" onclick="window.print()">Print</button>
        <button class="ghost" onclick="window.close()">Close</button>
    </div>

    @foreach ($codes->chunk(4) as $page)
        <div class="page">
            @foreach ($page as $code)
                @php [$amount, $word] = $period($code); @endphp
                <div class="cut">
                    <div class="v">
                        <div class="v-top">
                            <div class="brand"><img src="{{ asset('images/schoolhub-icon.svg') }}" alt=""><span class="name">School<em>Hub</em></span></div>
                            <div class="ribbon">GIFT VOUCHER</div>
                            <div class="gift"><b>{{ $amount }} {{ $word }}</b><small>{{ $code->plan?->name }} plan · after your free term</small></div>
                        </div>
                        <div class="v-body">
                            <p class="incl">
                                Up to <b>{{ \App\Models\Plan::limitLabel($code->plan?->max_students) }} learners</b> and
                                <b>{{ \App\Models\Plan::limitLabel($code->plan?->max_users) }} staff logins</b>, with every module:
                                fees, report cards, attendance, payroll, SMS to parents, library and sick bay.
                            </p>
                            <div class="code"><small>YOUR CODE</small><strong>{{ $code->code }}</strong></div>
                            <ol class="steps">
                                <li>Register your school at <b>{{ $site }}</b> (or sign in).</li>
                                <li>Open <b>Subscription</b>, choose <b>{{ $code->plan?->name }}</b> and tap <b>Activate with a code</b>.</li>
                                <li>Type the code above.</li>
                            </ol>
                            <p class="note">It starts when your free term ends, so you lose nothing by using it early. One school per code.</p>
                            <div class="v-foot">
                                <div>Call or WhatsApp<b>{{ $contact['phone'] ?? '' }}</b></div>
                                <div class="valid">Use before<b>{{ $code->expires_at?->format('j M Y') }}</b></div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
</body>
</html>
