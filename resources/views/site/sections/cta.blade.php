    {{-- ── CTA ── --}}
    <section class="container">
        <div class="cta" data-reveal>
            <h2>Ready to run your school the modern way?</h2>
            <p>Register in about a minute and try every module free for {{ $trialDays }} days. No payment details needed.</p>
            <div class="hero-actions">
                @if ($signedIn)
                    <a href="{{ $dashboardUrl }}" class="btn btn-white btn-lg">Open your dashboard {!! $arrow !!}</a>
                @else
                    <a href="{{ $registerUrl }}" class="btn btn-white btn-lg">Register your school {!! $arrow !!}</a>
                    <a href="{{ $demoUrl }}" class="btn btn-outline-white btn-lg">Book a demo</a>
                @endif
            </div>
        </div>
    </section>
