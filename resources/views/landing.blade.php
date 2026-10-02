{{--
    SchoolHub public landing page (site root): the hero and the facts, then
    the shared sections (resources/views/site), which the other public
    pages reuse. No Filament chrome or build step. Served by
    LandingController with App\Support\PublicSite::viewData().

    Palette: slate neutrals + one blue primary (the app panel's
    Color::Blue), deepened towards indigo in gradients, with the logo's
    amber used only as a small accent.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SchoolHub — School management software for Ugandan schools</title>
    <meta name="description" content="Students, fees, exams and report cards, payroll and finance for Ugandan primary and secondary schools. Register your school and try every module free for {{ $trialDays }} days.">
    <meta name="theme-color" content="#ffffff">
    <meta property="og:title" content="SchoolHub — School management software for Ugandan schools">
    <meta property="og:description" content="Fees, exams, report cards, payroll and finance in one system. Free {{ $trialDays }}-day trial.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ rtrim((string) config('app.url'), '/') }}/">
    @include('partials.site-head', ['path' => '/'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
@include('site.partials.styles')
    </style>
</head>
<body>

@include('site.partials.header', ['page' => 'home'])

<main id="main">

    {{-- ── Hero ── --}}
    <section class="hero">
        <div class="container hero-grid">
            <div class="hero-copy">
                <span class="badge"><b>New</b> Built for the lower-secondary competency curriculum</span>
                <h1>Run your whole school from <span>one smart system</span></h1>
                <p class="hero-lead">SchoolHub works out grades, positions, fee balances and PAYE for you — so your head teacher, bursar and teachers spend less time on paperwork and more time on learners. Built for Ugandan primary and secondary schools.</p>

                <div class="hero-actions">
                    @if ($signedIn)
                        <a href="{{ $dashboardUrl }}" class="btn btn-primary btn-lg">Open your dashboard {!! $arrow !!}</a>
                    @else
                        <a href="{{ $registerUrl }}" class="btn btn-primary btn-lg">Register your school {!! $arrow !!}</a>
                        <a href="#demo" class="btn btn-secondary btn-lg">Book a free demo</a>
                    @endif
                </div>

                @unless ($signedIn)
                    <p class="hero-signin">Already registered? <a href="{{ $loginUrl }}">Sign in to your school</a>@if ($contact['windows_download_enabled'] ?? false) · No internet? <a href="#windows">Get SchoolHub for Windows</a>@endif</p>
                @endunless

                <div class="hero-points">
                    <span>{!! $check !!} {{ $trialDays }}-day free trial</span>
                    <span>{!! $check !!} No payment details needed</span>
                    <span>{!! $check !!} Set up in minutes</span>
                    <span>{!! $check !!} Works on any phone or computer</span>
                </div>
            </div>

            <div class="preview" aria-hidden="true">
                <div class="preview-float pf-1">
                    <span class="dot green">{!! $check !!}</span>
                    <span><b>Report cards ready</b>S.2 East · positions worked out</span>
                </div>
                <div class="preview-float pf-2">
                    <span class="dot"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M1 4a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V4Zm12 4a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM4 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm13-1a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM1.75 14.5a.75.75 0 0 0 0 1.5c4.417 0 8.693.603 12.749 1.73 1.111.309 2.251-.512 2.251-1.696v-.784a.75.75 0 0 0-1.5 0v.784a.272.272 0 0 1-.35.25A49.043 49.043 0 0 0 1.75 14.5Z" clip-rule="evenodd"/></svg></span>
                    <span><b>Payroll calculated</b>PAYE &amp; NSSF on 38 payslips</span>
                </div>
                <div class="preview-float pf-3">
                    <span class="dot amber"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.93 2.31a41.401 41.401 0 0 1 10.14 0C16.194 2.45 17 3.414 17 4.517V17.25a.75.75 0 0 1-1.075.676l-2.8-1.344-2.8 1.344a.75.75 0 0 1-.65 0l-2.8-1.344-2.8 1.344A.75.75 0 0 1 3 17.25V4.517c0-1.103.806-2.068 1.93-2.207Zm4.822 3.997a.75.75 0 1 0-1.004-1.114l-2.5 2.25a.75.75 0 0 0 0 1.114l2.5 2.25a.75.75 0 0 0 1.004-1.114L8.704 8.75h1.921a1.875 1.875 0 0 1 0 3.75.75.75 0 0 0 0 1.5 3.375 3.375 0 1 0 0-6.75h-1.92l1.047-.943Z" clip-rule="evenodd"/></svg></span>
                    <span><b>Receipt printed</b>Balance updated instantly</span>
                </div>
                <div class="window">
                    <div class="window-bar">
                        <div class="dots"><i></i><i></i><i></i></div>
                        <div class="url">schoolhub · Dashboard</div>
                    </div>
                    <div class="app">
                        <aside class="app-side">
                            <img src="{{ asset('images/schoolhub-logo-light-cropped.svg') }}" alt="">
                            <a class="on"><i></i>Dashboard</a>
                            <p>Students</p>
                            <a><i></i>Students</a>
                            <a><i></i>Promotion</a>
                            <p>Fees</p>
                            <a><i></i>Receive payment</a>
                            <a><i></i>Fee balances</a>
                            <p>Exams &amp; Results</p>
                            <a><i></i>Enter marks</a>
                            <a><i></i>Report cards</a>
                            <p>Human Resources</p>
                            <a><i></i>Payroll</a>
                        </aside>
                        <div class="app-main">
                            <div class="app-head">
                                <div><strong>Dashboard</strong><br><span>Term II · 2026</span></div>
                                <span class="chip">+ Receive payment</span>
                            </div>
                            <div class="kpis">
                                <div class="kpi"><small>Active students</small><b>642</b><em>+18 this term</em></div>
                                <div class="kpi"><small>Fees collected</small><b>UGX 84.2M</b><em>71% of billed</em></div>
                                <div class="kpi"><small>Outstanding</small><b>UGX 34.6M</b><em class="warn">212 students</em></div>
                                <div class="kpi"><small>Staff on payroll</small><b>38</b><em>Payroll ready</em></div>
                            </div>
                            <div class="app-row">
                                <div class="panel">
                                    <div class="panel-title">Fee collection by week</div>
                                    <div class="chart">
                                        <i style="height:28%"></i><i style="height:46%"></i><i style="height:40%"></i><i style="height:62%"></i><i style="height:55%"></i><i style="height:74%"></i><i style="height:68%"></i><i style="height:88%"></i><i style="height:60%"></i><i class="muted" style="height:45%"></i><i class="muted" style="height:38%"></i><i class="muted" style="height:30%"></i>
                                    </div>
                                </div>
                                <div class="panel">
                                    <div class="panel-title">Recent payments</div>
                                    <div class="list">
                                        <div><span><b>Nakato S.</b> · S.2</span><span class="tag tag-green">450,000</span></div>
                                        <div><span><b>Okello B.</b> · P.7</span><span class="tag tag-green">320,000</span></div>
                                        <div><span><b>Achieng M.</b> · S.4</span><span class="tag tag-amber">Part paid</span></div>
                                        <div><span><b>Mugisha D.</b> · P.5</span><span class="tag tag-green">275,000</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Facts ── --}}
    <section class="facts" aria-label="SchoolHub at a glance">
        <div class="container facts-grid">
            <div class="fact"><b>9</b><span>Modules in one system</span></div>
            <div class="fact"><b>{{ $trialDays }} days</b><span>Free trial, all features</span></div>
            <div class="fact"><b>Nursery–S.6</b><span>Primary &amp; secondary</span></div>
            <div class="fact"><b>UGX</b><span>Priced per term</span></div>
        </div>
    </section>

    @include('site.sections.automation')

    @include('site.sections.features')

    @include('site.sections.modules')

    @include('site.sections.how')

    @include('site.sections.windows')

    @include('site.sections.pricing')

    @include('site.sections.faq')

    @include('site.sections.demo')

    @include('site.sections.cta')

</main>

@include('site.partials.footer')
</body>
</html>
