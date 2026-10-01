{{-- The public site's header and menu. $page: the page being shown, to mark it in the menu. --}}
<a href="#main" class="sr-only">Skip to content</a>

<header class="header" id="header">
    <div class="container header-inner">
        <a href="{{ route('filament.app.landing') }}" class="brand" aria-label="SchoolHub home">
            <img src="{{ asset('images/schoolhub-logo-light-cropped.svg') }}" alt="SchoolHub" width="185" height="44">
        </a>

        <nav class="nav" aria-label="Main">
            @foreach (\App\Support\PublicSite::PAGES as $slug => [$label])
                <a href="{{ route('filament.app.site.page', $slug) }}" @if (($page ?? null) === $slug) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <div class="header-actions">
            @if ($signedIn)
                <a href="{{ $dashboardUrl }}" class="btn btn-primary btn-sm header-keep">Go to dashboard</a>
            @else
                <a href="{{ $loginUrl }}" class="link-signin">Sign in</a>
                <a href="{{ $demoUrl }}" class="btn btn-secondary btn-sm">Book a demo</a>
                <a href="{{ $registerUrl }}" class="btn btn-primary btn-sm">Start free trial</a>
            @endif
            <button type="button" class="menu-btn" id="menu-btn" aria-expanded="false" aria-controls="mobile-nav" aria-label="Open menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
        </div>
    </div>

    <div class="mobile-nav" id="mobile-nav">
        @foreach (\App\Support\PublicSite::PAGES as $slug => [$label])
            <a href="{{ route('filament.app.site.page', $slug) }}" @if (($page ?? null) === $slug) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
        <a href="{{ $demoUrl }}">Book a demo</a>
        @if ($signedIn)
            <a href="{{ $dashboardUrl }}" class="btn btn-primary btn-block">Go to dashboard</a>
        @else
            <a href="{{ $registerUrl }}" class="btn btn-primary btn-block">Start free trial</a>
            <a href="{{ $loginUrl }}" class="btn btn-secondary btn-block">Sign in</a>
        @endif
    </div>
</header>
