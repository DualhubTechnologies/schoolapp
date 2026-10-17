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
    <title>SchoolHub - School management software for Ugandan schools</title>
    <meta name="description" content="Run your school smarter: fees, income and spending, learners and marks in one place, with new-curriculum O-Level and A-Level report cards generated in seconds. For Ugandan primary and secondary schools. Free for {{ $trialDays }} days.">
    <meta name="theme-color" content="#ffffff">
    <meta property="og:title" content="SchoolHub - School management software for Ugandan schools">
    <meta property="og:description" content="Income against expenditure, fees, payroll and new-curriculum O-Level and A-Level report cards in one system. Free {{ $trialDays }}-day trial.">
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

    @php
        // Outline icons (24px, Heroicons) used by the hero, the dashboard picture and the strips below it.
        $icons = [
            'home' => 'm2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
            'users' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
            'card' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z',
            'cap' => 'M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5',
            'doc' => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
            'user' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z',
            'grid' => 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z',
            'receipt' => 'M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185ZM9.75 9h.008v.008H9.75V9Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm4.125 4.5h.008v.008h-.008V13.5Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z',
            'chat' => 'M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z',
            'chart' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
            'cog' => 'M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75',
            'search' => 'm21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z',
            'bell' => 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0',
            'cash' => 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z',
            'cart' => 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z',
            'alert' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
            'puzzle' => 'M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 0 1-.657.643 48.39 48.39 0 0 1-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 0 1-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 0 0-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.039 48.039 0 0 1-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 0 0 .657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 0 1-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.4.604-.4.959v0c0 .333.277.599.61.58a48.1 48.1 0 0 0 5.427-.63 48.05 48.05 0 0 0 .582-4.717.532.532 0 0 0-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.96.401v0a.656.656 0 0 0 .658-.663 48.422 48.422 0 0 0-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 0 1-.61-.58v0Z',
            'calendar' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5',
            'wallet' => 'M21 12a2.25 2.25 0 0 0-2.25-2.25H15a3 3 0 1 1-6 0H5.25A2.25 2.25 0 0 0 3 12m18 0v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 9m18 0V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v3',
            'library' => 'M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z',
            'moon' => 'M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z',
            'phone' => 'M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3',
            'pin' => 'M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z',
            'book' => 'M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25',
        ];
        $ico = fn (string $name): string => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="'.$icons[$name].'"/></svg>';
        $tick = '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/></svg>';
    @endphp

    {{-- ── Hero ── --}}
    <section class="hero">
        <div class="container hero-grid">
            <div class="hero-copy">
                <span class="badge"><b>New curriculum</b> O-Level and A-Level report cards, worked out for you</span>
                <h1><span class="h1-line h1-wrap">Fees, marks and payroll.</span> <span>One system.</span></h1>
                <p class="hero-lead">Receive fees with instant receipts, turn marks into curriculum-aligned report cards in seconds, and run payroll with PAYE and NSSF worked out. Built for Ugandan primary and secondary schools.</p>

                <div class="hero-actions">
                    @if ($signedIn)
                        <a href="{{ $dashboardUrl }}" class="btn btn-primary btn-lg">Open your dashboard {!! $arrow !!}</a>
                    @else
                        <a href="{{ $registerUrl }}" class="btn btn-primary btn-lg">Start your {{ $trialDays }}-day free trial {!! $arrow !!}</a>
                        <a href="#demo" class="btn btn-secondary btn-lg btn-demo"><span class="btn-demo-icon">{!! $ico('calendar') !!}</span> Book a free demo</a>
                    @endif
                </div>

                @if ($contact['windows_download_enabled'] ?? false)
                    <p class="hero-signin">No internet? <a href="#windows">Get SchoolHub for Windows</a></p>
                @endif

                <ul class="hero-ticks">
                    <li>{!! $tick !!} No payment details needed</li>
                    <li>{!! $tick !!} Set up in minutes</li>
                    <li>{!! $tick !!} Works on any device</li>
                    <li>{!! $tick !!} Full access to all features</li>
                </ul>
            </div>

            <div class="preview" aria-hidden="true">
                <div class="preview-float pf-1">
                    <span class="dot green">{!! $check !!}</span>
                    <span><b>Report cards ready</b>S.2 East · 48 learners, in seconds</span>
                    <i class="pf-x">&times;</i>
                </div>
                <div class="preview-float pf-3">
                    <span class="dot amber">{!! $ico('receipt') !!}</span>
                    <span><b>Receipt printed</b>Balance updated instantly</span>
                </div>
                <div class="window">
                    <div class="window-bar">
                        <div class="dots"><i></i><i></i><i></i></div>
                    </div>
                    <div class="app">
                        <aside class="app-side">
                            <div class="app-brand"><img src="{{ asset('images/schoolhub-icon.svg') }}" alt=""> <span>School<em>Hub</em></span></div>
                            <a class="on">{!! $ico('home') !!} Dashboard</a>
                            <a>{!! $ico('users') !!} Students</a>
                            <a>{!! $ico('card') !!} Fees &amp; Payments</a>
                            <a>{!! $ico('cap') !!} Academics</a>
                            <a>{!! $ico('doc') !!} Report Cards</a>
                            <a>{!! $ico('user') !!} Staff</a>
                            <a>{!! $ico('grid') !!} Classes</a>
                            <a>{!! $ico('receipt') !!} Expenses</a>
                            <a>{!! $ico('chat') !!} Messages</a>
                            <a>{!! $ico('chart') !!} Reports</a>
                            <a>{!! $ico('cog') !!} Settings</a>
                        </aside>
                        <div class="app-body">
                            <div class="app-top">
                                <div class="app-search">{!! $ico('search') !!} Search students, parents, payments…</div>
                                <div class="app-bell">{!! $ico('bell') !!}<i>3</i></div>
                                <div class="app-user"><span>JS</span><div><b>John S.</b><small>Headteacher</small></div></div>
                            </div>
                            <div class="app-main">
                                <div class="app-head">
                                    <div><strong>Dashboard</strong><br><span>Term II · 2026</span></div>
                                    <span class="chip">+ Record payment</span>
                                </div>
                                <div class="kpis">
                                    <div class="kpi"><small><i class="k-green">{!! $ico('cash') !!}</i> <span class="l-full">Income this term</span><span class="l-short">Income</span></small><b>UGX 87.3M</b><em>↑ 12% from last term</em></div>
                                    <div class="kpi"><small><i class="k-blue">{!! $ico('cart') !!}</i> <span class="l-full">Total spent</span><span class="l-short">Spent</span></small><b>UGX 61.9M</b><em class="plain">93% of budget</em></div>
                                    <div class="kpi"><small><i class="k-red">{!! $ico('alert') !!}</i> <span class="l-full">Outstanding fees</span><span class="l-short">Fees owed</span></small><b>UGX 34.6M</b><em class="warn">212 students</em></div>
                                    <div class="kpi"><small><i class="k-teal">{!! $ico('users') !!}</i> <span class="l-full">Active students</span><span class="l-short">Students</span></small><b>642</b><em>↑ 18 this term</em></div>
                                </div>
                                <div class="app-row">
                                    <div class="panel">
                                        <div class="panel-title">Fee collection by week <span class="panel-chip">This term</span></div>
                                        <div class="chart-wrap">
                                            <div class="chart-y"><span>60M</span><span>40M</span><span>20M</span><span>0</span></div>
                                            <div class="chart">
                                                <i style="height:22%"></i><i style="height:40%"></i><i style="height:52%"></i><i style="height:64%"></i><i style="height:78%"></i><i style="height:92%"></i><i style="height:74%"></i><i class="muted" style="height:60%"></i><i class="muted" style="height:34%"></i>
                                            </div>
                                        </div>
                                        <div class="chart-x"><span>Week 1</span><span>2</span><span>3</span><span>4</span><span>5</span><span>6</span><span>7</span><span>8</span><span>9</span></div>
                                    </div>
                                    <div class="panel">
                                        <div class="panel-title">Recent payments <span class="panel-link">View all</span></div>
                                        <div class="list">
                                            <div><span class="av av-blue">N</span><span class="who"><b>Nakato S.</b> · S.2</span><span class="amt">450,000</span><span class="tag tag-green">Paid</span></div>
                                            <div><span class="av av-red">O</span><span class="who"><b>Okello B.</b> · P.7</span><span class="amt">320,000</span><span class="tag tag-green">Paid</span></div>
                                            <div><span class="av av-amber">A</span><span class="who"><b>Achieng M.</b> · S.4</span><span class="amt">275,000</span><span class="tag tag-amber">Part paid</span></div>
                                            <div><span class="av av-purple">M</span><span class="who"><b>Mugisha D.</b> · P.5</span><span class="amt">275,000</span><span class="tag tag-green">Paid</span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── At a glance ── --}}
    <section class="glance" aria-label="SchoolHub at a glance">
        <div class="container glance-grid">
            <div class="glance-card" data-reveal><span class="gi gi-green">{!! $ico('puzzle') !!}</span><div><b>9 integrated modules</b><span>Everything in one platform</span></div></div>
            <div class="glance-card" data-reveal><span class="gi gi-blue">{!! $ico('calendar') !!}</span><div><b>{{ $trialDays }}-day free trial</b><span>Explore every feature</span></div></div>
            <div class="glance-card" data-reveal><span class="gi gi-purple">{!! $ico('cap') !!}</span><div><b>Nursery to S.6</b><span>Built for Ugandan schools</span></div></div>
            <div class="glance-card" data-reveal><span class="gi gi-amber">{!! $ico('card') !!}</span><div><b>Flexible UGX pricing</b><span>Simple per-term plans</span></div></div>
        </div>
    </section>

    {{-- ── Built for every kind of Ugandan school (not school logos: none is claimed as a customer) ── --}}
    <section class="kinds" aria-label="Built for every Ugandan school">
        <div class="container">
            <h2>Made for schools across Uganda</h2>
            <p>Helping schools simplify administration and focus on better education, whatever their level and size.</p>
            <div class="kinds-row">
                <div class="kind">{!! $ico('book') !!}<span><b>Nursery &amp; Primary</b>PLE aggregates &amp; divisions</span></div>
                <div class="kind">{!! $ico('cap') !!}<span><b>O-Level</b>New lower-secondary curriculum</span></div>
                <div class="kind">{!! $ico('library') !!}<span><b>A-Level</b>UACE grades &amp; points</span></div>
                <div class="kind">{!! $ico('moon') !!}<span><b>Day &amp; boarding</b>Fees by residency</span></div>
                <div class="kind">{!! $ico('phone') !!}<span><b>Any device</b>Phone, tablet or computer</span></div>
                <div class="kind">{!! $ico('pin') !!}<span><b>Made in Uganda</b>UGX, PAYE, NSSF, UNEB</span></div>
            </div>
        </div>
    </section>

    @include('site.sections.problems')

    @include('site.sections.features')

    @include('site.sections.how')

    @include('site.sections.automation')

    @include('site.sections.insights')

    @include('site.sections.modules')

    @include('site.sections.designer')

    @include('site.sections.windows')

    @include('site.sections.pricing')

    @include('site.sections.faq')

    @include('site.sections.demo')

    @include('site.sections.cta')

</main>

@include('site.partials.footer')
</body>
</html>
