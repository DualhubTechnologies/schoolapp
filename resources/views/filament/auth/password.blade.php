{{-- "Forgot your password?" and "Choose a new password", in the sign-in design. --}}
<x-auth-shell :heading="$this->getHeading()" :subheading="$this->getSubheading()">
    {{ $this->content }}

    @if ($this instanceof \App\Filament\Pages\Auth\RequestPasswordReset && filament()->getId() !== 'admin')
        <div class="sha-note">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg>
            <span><strong>Parents</strong> sign in with a PIN, not a password: ask the school office to send you a new one.</span>
        </div>
    @endif

    <div class="sha-alt">
        Remembered it? <a href="{{ filament()->getLoginUrl() }}">Back to sign in</a>
    </div>
</x-auth-shell>
