@php $isPlatform = filament()->getId() === 'admin'; @endphp

<x-auth-shell
    :eyebrow="$isPlatform ? 'SchoolHub staff' : 'Sign in'"
    :heading="$isPlatform ? 'Platform sign in' : 'Welcome back'"
    :subheading="$isPlatform ? 'For SchoolHub staff managing schools and subscriptions.' : 'Sign in to your school\'s SchoolHub account.'"
>
    {{ $this->content }}

    @unless ($isPlatform)
        <div class="sha-note">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg>
            <span><strong>Parents:</strong> sign in with your phone number and the PIN the school sent you. Forgot your PIN? Ask the school office to send you a new one.</span>
        </div>
    @endunless

    @if (filament()->hasRegistration())
        <div class="sha-or">New to SchoolHub?</div>
        <a href="{{ filament()->getRegistrationUrl() }}" class="sha-btn-outline">
            Register your school: {{ config('subscriptions.trial_days') }} days free
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd"/></svg>
        </a>
    @endif
</x-auth-shell>
