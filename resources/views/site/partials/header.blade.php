{{-- The public site's header and menu. $page: the page being shown, to mark it in the menu. --}}
<a href="#main" class="sr-only">Skip to content</a>

<header class="header" id="header">
    <div class="container header-inner">
        <a href="{{ route('filament.app.landing') }}" class="brand" aria-label="SchoolHub home">
            <img src="{{ asset('images/schoolhub-logo-sidebar.svg') }}" alt="SchoolHub" width="185" height="44">
        </a>

        <nav class="nav" aria-label="Main">
            @foreach (\App\Support\PublicSite::PAGES as $slug => [$label])
                @if ($slug === 'features')
                    {{-- Features, with the main parts of the product one click away. --}}
                    <div class="nav-drop">
                        <a href="{{ route('filament.app.site.page', $slug) }}" @if (($page ?? null) === $slug) aria-current="page" @endif>{{ $label }}</a><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/></svg>
                        <div class="nav-menu">
                            <a href="{{ route('filament.app.site.page', 'features') }}#finance"><b>Income &amp; expenditure</b><span>Fees, expenses and budgets in one place</span></a>
                            <a href="{{ route('filament.app.site.page', 'features') }}#o-level"><b>Report cards</b><span>O-Level and A-Level, generated in seconds</span></a>
                            <a href="{{ route('filament.app.site.page', 'features') }}#fees"><b>Fees &amp; receipts</b><span>Balances, receipts and reminders</span></a>
                            <a href="{{ route('filament.app.site.page', 'features') }}#payroll"><b>Payroll</b><span>PAYE, NSSF and LST worked out</span></a>
                            <a href="{{ route('filament.app.site.page', 'features') }}" class="nav-menu-all">All features &rarr;</a>
                        </div>
                    </div>
                    <a href="{{ route('filament.app.landing') }}#how">How it works</a>
                @elseif ($slug === 'about')
                    {{-- About us, with the team beneath it. --}}
                    <div class="nav-drop">
                        <a href="{{ route('filament.app.site.page', $slug) }}" @if (($page ?? null) === $slug) aria-current="page" @endif>{{ $label }}</a><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/></svg>
                        <div class="nav-menu nav-menu-sm">
                            <a href="{{ route('filament.app.site.page', 'about') }}"><b>About SchoolHub</b><span>Who we are and how we look after your data</span></a>
                            <a href="{{ route('filament.app.site.page', 'team') }}" @if (($page ?? null) === 'team') aria-current="page" @endif>{{ \App\Support\PublicSite::PAGES['team'][0] }}</a>
                        </div>
                    </div>
                @elseif ($slug === 'team')
                    {{-- Under About us. --}}
                @else
                    <a href="{{ route('filament.app.site.page', $slug) }}" @if (($page ?? null) === $slug) aria-current="page" @endif>{{ $label }}</a>
                @endif
            @endforeach
        </nav>

        <div class="header-actions">
            @if ($signedIn)
                <a href="{{ $dashboardUrl }}" class="btn btn-primary btn-sm header-keep">Go to dashboard</a>
            @else
                <a href="{{ $loginUrl }}" class="link-signin">Sign in</a>
                <a href="{{ $demoUrl }}" class="btn btn-secondary btn-sm">Book a demo</a>
                <a href="{{ $registerUrl }}" class="btn btn-primary btn-sm">Start your free term {!! $arrow !!}</a>
            @endif
            <button type="button" class="menu-btn" id="menu-btn" aria-expanded="false" aria-controls="mobile-nav" aria-label="Open menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
        </div>
    </div>

    <div class="mobile-nav" id="mobile-nav">
        @foreach (\App\Support\PublicSite::PAGES as $slug => [$label])
            <a href="{{ route('filament.app.site.page', $slug) }}" @if (($page ?? null) === $slug) aria-current="page" @endif>{{ $label }}</a>
            @if ($slug === 'features')<a href="{{ route('filament.app.landing') }}#how">How it works</a>@endif
        @endforeach
        <a href="{{ $demoUrl }}">Book a demo</a>
        @if ($signedIn)
            <a href="{{ $dashboardUrl }}" class="btn btn-primary btn-block">Go to dashboard</a>
        @else
            <a href="{{ $registerUrl }}" class="btn btn-primary btn-block">Start your free term</a>
            <a href="{{ $loginUrl }}" class="btn btn-secondary btn-block">Sign in</a>
        @endif
    </div>
</header>
