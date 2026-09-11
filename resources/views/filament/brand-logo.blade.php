{{-- Full logo on light card (login page) --}}
<img src="{{ asset('images/schoolhub-logo-light.svg') }}"
     alt="SchoolHub"
     class="h-8 fi-simple-page-header-logo" />

{{-- Full logo on dark topbar (admin) --}}
<img src="{{ asset('images/schoolhub-logo-dark.svg') }}"
     alt="SchoolHub"
     class="h-8 fi-topbar-logo hidden" />

<style>
    /* On the admin topbar, show the dark-bg variant */
    .fi-topbar .fi-topbar-logo { display: block !important; }
    .fi-topbar .fi-simple-page-header-logo { display: none !important; }
</style>