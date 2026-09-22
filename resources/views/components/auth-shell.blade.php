{{--
    SchoolHub sign-in / registration shell: brand panel on the left, the
    form on the right (the brand panel folds away on tablets and phones).

    Pass a `side` slot to replace the brand panel's middle content (the
    registration page does); otherwise the general product pitch shows.
--}}
@props(['heading', 'subheading' => null, 'wide' => false])

@php
    $contact = config('contact');
    $waUrl = 'https://wa.me/' . $contact['whatsapp'] . '?text=' . rawurlencode('Hello, I need help with SchoolHub.');
@endphp

<div class="sha">
    <aside class="sha-side">
        <a href="{{ url('/') }}" class="sha-logo"><img src="{{ asset('images/schoolhub-logo-sidebar.svg') }}" alt="SchoolHub"></a>

        <div class="sha-side-body">
            @if (isset($side) && $side->isNotEmpty())
                {{ $side }}
            @else
                <h1>Run your whole school from one place.</h1>
                <p class="sha-lead">Fees, exams and report cards, payroll and finance — built for Ugandan primary and secondary schools.</p>

                <ul class="sha-points">
                    <li>
                        <span class="sha-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z"/></svg></span>
                        <div><strong>Fees &amp; receipts</strong><span>Billing, balances, receipts and reminder letters.</span></div>
                    </li>
                    <li>
                        <span class="sha-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342"/></svg></span>
                        <div><strong>Exams &amp; report cards</strong><span>PLE aggregates, the new O-Level curriculum and A-Level points.</span></div>
                    </li>
                    <li>
                        <span class="sha-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg></span>
                        <div><strong>Staff &amp; payroll</strong><span>PAYE, NSSF and LST worked out for you, with payslips.</span></div>
                    </li>
                </ul>
            @endif
        </div>

        <div class="sha-side-foot">
            <a href="{{ $waUrl }}" class="sha-help" target="_blank" rel="noopener">
                <span class="sha-help-ic"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg></span>
                <span><strong>Need help?</strong> WhatsApp or call {{ $contact['phone'] }}</span>
            </a>
            <div class="sha-copy">© {{ date('Y') }} SchoolHub · {{ $contact['company'] }}</div>
        </div>
    </aside>

    <main class="sha-main">
        <div class="sha-topline">
            <a href="{{ url('/') }}" class="sha-back">
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.612l4.158 3.96a.75.75 0 1 1-1.04 1.08l-5.5-5.25a.75.75 0 0 1 0-1.08l5.5-5.25a.75.75 0 1 1 1.04 1.08L5.612 9.25H16.25A.75.75 0 0 1 17 10Z" clip-rule="evenodd"/></svg>
                Back to home
            </a>
            <a href="{{ url('/') }}" class="sha-logo-mobile"><img src="{{ asset('images/schoolhub-logo-light-cropped.svg') }}" alt="SchoolHub"></a>
        </div>

        <div @class(['sha-card', 'sha-card-wide' => $wide])>
            <h2 class="sha-h">{{ $heading }}</h2>
            @if ($subheading)<p class="sha-sub">{{ $subheading }}</p>@endif

            {{ $slot }}
        </div>
    </main>
</div>

@once
<style>
    .sha {
        --sha-ink: #0f172a; --sha-text: #334155; --sha-muted: #64748b; --sha-line: #e2e8f0; --sha-soft: #f1f5f9;
        --sha-blue: #2563eb; --sha-blue-dark: #1d4ed8; --sha-blue-soft: #eff6ff;
        min-height: 100vh; display: grid; grid-template-columns: minmax(22rem, 32%) 1fr; background: #f8fafc; color: var(--sha-text);
    }

    /* Brand panel */
    .sha-side { position: sticky; top: 0; height: 100vh; display: flex; flex-direction: column; gap: 2rem; padding: 2.5rem 3rem; color: #cbd5e1; background: linear-gradient(165deg, #1e3a8a 0%, #172554 45%, #0f172a 100%); overflow: hidden; }
    .sha-side::before { content: ""; position: absolute; inset: 0; background-image: linear-gradient(rgba(255, 255, 255, .045) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, .045) 1px, transparent 1px); background-size: 2.75rem 2.75rem; mask-image: linear-gradient(to bottom, #000, transparent 85%); -webkit-mask-image: linear-gradient(to bottom, #000, transparent 85%); pointer-events: none; }
    .sha-side > * { position: relative; }
    .sha-logo img { height: 2.75rem; width: auto; }
    .sha-side-body { flex: 1; display: flex; flex-direction: column; justify-content: center; }
    .sha-side h1 { font-size: 2rem; line-height: 1.2; font-weight: 800; color: #fff; letter-spacing: -.025em; max-width: 26rem; }
    .sha-lead { margin-top: .9rem; font-size: 1rem; line-height: 1.6; color: #bfdbfe; max-width: 27rem; }
    .sha-points { list-style: none; margin: 2rem 0 0; padding: 0; display: flex; flex-direction: column; gap: 1.1rem; }
    .sha-points li { display: flex; gap: .9rem; align-items: flex-start; }
    .sha-points strong { display: block; color: #fff; font-size: .95rem; font-weight: 600; }
    .sha-points span { font-size: .85rem; color: #94a3b8; line-height: 1.5; }
    .sha-ic { flex: none; display: grid; place-items: center; width: 2.4rem; height: 2.4rem; border-radius: .7rem; background: rgba(255, 255, 255, .08); border: 1px solid rgba(255, 255, 255, .12); color: #93c5fd; }
    .sha-ic svg { width: 1.2rem; height: 1.2rem; }

    .sha-side-foot { display: flex; flex-direction: column; gap: 1rem; }
    .sha-help { display: flex; align-items: center; gap: .75rem; padding: .85rem 1rem; border-radius: .8rem; background: rgba(255, 255, 255, .06); border: 1px solid rgba(255, 255, 255, .1); font-size: .85rem; color: #cbd5e1; text-decoration: none; transition: background .15s; }
    .sha-help:hover { background: rgba(255, 255, 255, .1); }
    .sha-help strong { color: #fff; font-weight: 600; }
    .sha-help-ic { flex: none; display: grid; place-items: center; width: 2rem; height: 2rem; border-radius: 50%; background: #25d366; color: #fff; }
    .sha-help-ic svg { width: 1.1rem; height: 1.1rem; }
    .sha-copy { font-size: .78rem; color: #64748b; }

    /* Form column */
    .sha-main { display: flex; flex-direction: column; min-height: 100vh; padding: 1rem 2.5rem; }
    .sha-topline { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: .75rem; }
    .sha-back { display: inline-flex; align-items: center; gap: .4rem; font-size: .875rem; font-weight: 500; color: var(--sha-muted); text-decoration: none; }
    .sha-back:hover { color: var(--sha-ink); }
    .sha-back svg { width: 1rem; height: 1rem; }
    .sha-logo-mobile { display: none; }
    .sha-logo-mobile img { height: 2.25rem; width: auto; }

    /* margin:auto centres the card when it is short and lets it scroll naturally when tall. */
    .sha-card { width: 100%; max-width: 28rem; margin: auto; background: #fff; border: 1px solid var(--sha-line); border-radius: 1rem; padding: 2.25rem; box-shadow: 0 1px 3px rgba(15, 23, 42, .06), 0 20px 40px -24px rgba(15, 23, 42, .22); }
    /* The wide (registration) card is sized to fit one screen on a laptop: three fields a row, tight rhythm. */
    .sha-card-wide { max-width: 54rem; padding: 1.5rem 2.25rem 1.25rem; }
    .sha-card-wide .sha-h { font-size: 1.5rem; }
    .sha-card-wide .sha-sub { margin-top: .25rem; margin-bottom: 1.25rem; }
    .sha-card-wide .fi-sc-has-gap { row-gap: .9rem; }
    .sha-card-wide .fi-section-header { padding-bottom: .75rem; }
    .sha-card-wide .fi-sc-component + .fi-sc-component:has(> .fi-sc-section) { margin-top: .25rem; padding-top: 1rem; }
    .sha-card-wide .fi-form-actions, .sha-card-wide .fi-ac { margin-top: .25rem; }
    .sha-card-wide .sha-alt { margin-top: 1rem; padding-top: .9rem; }
    .sha-h { font-size: 1.625rem; font-weight: 800; color: var(--sha-ink); letter-spacing: -.02em; line-height: 1.25; }
    .sha-sub { margin-top: .4rem; margin-bottom: 1.5rem; color: var(--sha-muted); font-size: .9375rem; line-height: 1.55; }
    .sha-alt { margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--sha-soft); text-align: center; font-size: .9rem; color: var(--sha-muted); }
    .sha-alt a { color: var(--sha-blue); font-weight: 600; text-decoration: none; }
    .sha-alt a:hover { color: var(--sha-blue-dark); text-decoration: underline; }
    .sha-note { display: flex; gap: .75rem; align-items: flex-start; margin-bottom: 1.75rem; padding: .9rem 1rem; border-radius: .75rem; background: var(--sha-blue-soft); border: 1px solid #dbeafe; color: #1e3a8a; font-size: .85rem; line-height: 1.5; }
    .sha-note svg { flex: none; width: 1.25rem; height: 1.25rem; margin-top: .05rem; color: var(--sha-blue); }

    /* Filament form inside the card */
    .sha .fi-btn { min-height: 2.875rem; font-weight: 600; border-radius: .5rem; }
    .sha .fi-input-wrp { border-radius: .5rem; }

    /* Form sections read as numbered steps, not boxes inside a box. */
    .sha-card { counter-reset: sha-step; }
    /* !important: the panel stylesheet (filament-custom.css) boxes every .fi-section with !important. */
    .sha .fi-section { counter-increment: sha-step; background: none !important; box-shadow: none !important; border: 0 !important; border-radius: 0 !important; padding: 0; }
    /* Each section sits in its own .fi-sc-component wrapper, so separate the wrappers. */
    .sha .fi-sc-component + .fi-sc-component:has(> .fi-sc-section) { padding-top: .75rem; border-top: 1px solid var(--sha-line); }
    .sha .fi-section-header { padding: 0 0 1rem; border: 0; }
    .sha .fi-section-header-text-ctn { position: relative; padding-left: 2.5rem; }
    .sha .fi-section-header-text-ctn::before { content: counter(sha-step); position: absolute; left: 0; top: .05rem; display: grid; place-items: center; width: 1.75rem; height: 1.75rem; border-radius: 50%; background: var(--sha-blue); color: #fff; font-size: .8rem; font-weight: 700; }
    .sha .fi-section-header-heading { font-size: 1rem; font-weight: 700; color: var(--sha-ink); }
    .sha .fi-section-header-description { margin-top: .15rem; font-size: .83rem; color: var(--sha-muted); }
    .sha .fi-section-content-ctn { border: 0; }
    .sha .fi-section-content { padding: 0; }

    /* Short laptop screens: keep everything on one screen. */
    @media (max-height: 780px) {
        .sha-side { padding-top: 1.75rem; padding-bottom: 1.75rem; gap: 1.25rem; }
        .sha-side h1 { font-size: 1.6rem; }
        .sha-side .shr-next { display: none; }
    }

    @media (max-width: 1100px) {
        .sha { grid-template-columns: minmax(20rem, 26rem) 1fr; }
        .sha-side { padding: 2rem; }
        .sha-side h1 { font-size: 1.65rem; }
    }
    @media (max-width: 900px) {
        .sha { grid-template-columns: 1fr; }
        .sha-side { display: none; }
        .sha-logo-mobile { display: inline-block; }
        .sha-back { order: 2; }
        .sha-main { padding: 1.25rem 1rem 2rem; }
        .sha-card, .sha-card-wide { margin-top: 1.25rem; padding: 1.75rem 1.25rem; }
    }
</style>
@endonce
