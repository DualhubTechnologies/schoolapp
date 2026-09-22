@php $isPlatform = filament()->getId() === 'admin'; @endphp

<x-auth-shell
    :heading="$isPlatform ? 'Platform sign in' : 'Welcome back'"
    :subheading="$isPlatform ? 'For SchoolHub staff managing schools and subscriptions.' : 'Sign in to your school\'s SchoolHub account.'"
>
    {{ $this->content }}

    @if (filament()->hasRegistration())
        <div class="sha-alt">
            New to SchoolHub? <a href="{{ filament()->getRegistrationUrl() }}">Register your school — free for {{ config('subscriptions.trial_days') }} days</a>
        </div>
    @endif
</x-auth-shell>
