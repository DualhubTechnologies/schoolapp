{{--
    SchoolHub — login brand panel
    Save as: resources/views/filament/partials/login-brand.blade.php

    The navy left-hand half of the split login card. Injected via the
    SIMPLE_PAGE_START render hook; the CSS turns .fi-simple-main into
    a two-column grid so this sits beside the login form.

    ICON PATH NOTE: this uses public/images/schoolhub-logo-light.svg,
    taken from your AdminPanelProvider's brandLogo(). If that file
    doesn't exist, swap it for whichever logo you actually have.
--}}

<div class="sh-login-brand">

    <img src="{{ asset('images/schoolhub-logo-dark.svg') }}" alt="SchoolHub">

    <h2>Welcome back</h2>

    <p>
        Sign in to SchoolHub — the complete school management system for
        students, staff, payroll and fees.
    </p>

    <div class="sh-login-links">
        <a href="{{ url('https://www.schoolhubug.com/') }}">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M2.25 12h19.5m-19.5 0a9.75 9.75 0 1019.5 0m-19.5 0a9.75 9.75 0 1119.5 0M12 2.25c2.3 2.54 3.6 5.98 3.6 9.75s-1.3 7.21-3.6 9.75c-2.3-2.54-3.6-5.98-3.6-9.75s1.3-7.21 3.6-9.75z" />
            </svg>
            Website
        </a>
        <a href="{{ url('/') }}">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M2.25 12l8.955-8.955a1.125 1.125 0 011.59 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
            </svg>
            Home
        </a>
    </div>

</div>
