@php
    $isPlatform = filament()->getId() === 'admin';
    // Said the way the school office would say it, by the time of day in Uganda.
    $hour = now('Africa/Kampala')->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
@endphp

<x-auth-shell
    :heading="$isPlatform ? 'Platform sign in' : $greeting"
    :subheading="$isPlatform ? 'For SchoolHub staff managing schools and subscriptions.' : 'Sign in to your school\'s SchoolHub account.'"
>
    {{ $this->content }}

    @unless ($isPlatform)
        {{-- Shown on computers only; the phone layout leaves it out. --}}
        <div class="sha-note sha-note-parents">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg>
            <span><strong>Parents:</strong> sign in with your phone number and the PIN the school sent you. Forgot your PIN? Ask the school office to send you a new one.</span>
        </div>
    @endunless

    @if (filament()->hasRegistration())
        <p class="sha-alt-register">
            New to SchoolHub? <a href="{{ filament()->getRegistrationUrl() }}">Register your school</a>, and the first term ({{ \App\Services\Subscriptions\SubscriptionManager::trialMonths() }} months) is free.
        </p>
    @endif
</x-auth-shell>
