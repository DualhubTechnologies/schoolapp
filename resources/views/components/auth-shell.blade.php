{{--
    SchoolHub sign-in / registration shell, in a classic style: a framed
    navy brand panel with gold rules and serif headings on the left, the
    form on ivory "paper" on the right. On tablets and phones the brand
    panel folds into a navy band behind the top of the card.

    Used by sign in, registration, email confirmation and both password
    pages. Pass a `side` slot to replace the brand panel's middle content
    (the registration page does); otherwise the general product pitch shows.

    Everything, the fonts and styles too, stays inside the one root <div>:
    Livewire binds the page to its first element, so anything before it
    would leave the form unbound.
--}}
@props(['heading', 'subheading' => null, 'eyebrow' => null, 'wide' => false])

@php
    $contact = config('contact');
    $waUrl = 'https://wa.me/' . $contact['whatsapp'] . '?text=' . rawurlencode('Hello, I need help with SchoolHub.');
    $copyright = '© ' . date('Y') . ' SchoolHub · ' . $contact['company'];
@endphp

<div class="sha">
    @once
        {{-- Serif for headings; without the network (desktop edition) it falls back to Georgia. --}}
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&display=swap">
        <style>
            .sha {
                --sha-navy: #13295b; --sha-navy-deep: #0b1a3a; --sha-ink: #13213f; --sha-text: #3b4658; --sha-muted: #6b7385;
                --sha-gold: #c4973a; --sha-gold-light: #e2c47e; --sha-gold-soft: #fbf5e6; --sha-gold-line: #ead8ab;
                --sha-paper: #f6f3ec; --sha-line: #e5dfd2; --sha-soft: #efeae0;
                --sha-serif: "Libre Baskerville", Georgia, "Times New Roman", serif;
                /* Filament's primary colour, navy inside the sign-in screens: the button, links, checkboxes and focus rings. */
                --primary-50: #f1f4fa; --primary-100: #dfe6f2; --primary-200: #c2cfe5; --primary-300: #96abd0; --primary-400: #6583b5;
                --primary-500: #3f5f95; --primary-600: #1c3a74; --primary-700: #162f5f; --primary-800: #12274f; --primary-900: #0f2142; --primary-950: #0a1630;
                min-height: 100vh; display: grid; grid-template-columns: minmax(23rem, 36%) 1fr; background: var(--sha-paper); color: var(--sha-text);
            }

            /* ── Brand panel: navy, a fine engraved lattice, a gold frame ── */
            .sha-side {
                position: sticky; top: 0; height: 100vh; display: flex; flex-direction: column; gap: 2rem; padding: 3rem 3.5rem 2.75rem; color: #c9d3e6; overflow: hidden;
                background:
                    radial-gradient(ellipse at 15% 0%, rgba(98, 132, 214, .28), transparent 55%),
                    radial-gradient(ellipse at 100% 100%, rgba(196, 151, 58, .12), transparent 50%),
                    linear-gradient(170deg, #17336e 0%, var(--sha-navy) 40%, var(--sha-navy-deep) 100%);
            }
            .sha-side::before {
                content: ""; position: absolute; inset: 0; pointer-events: none; opacity: .5;
                background-image:
                    repeating-linear-gradient(45deg, rgba(226, 196, 126, .07) 0 1px, transparent 1px 22px),
                    repeating-linear-gradient(-45deg, rgba(226, 196, 126, .07) 0 1px, transparent 1px 22px);
                mask-image: radial-gradient(ellipse at 50% 40%, #000 20%, transparent 80%); -webkit-mask-image: radial-gradient(ellipse at 50% 40%, #000 20%, transparent 80%);
            }
            .sha-side::after { content: ""; position: absolute; inset: 1.15rem; border: 1px solid rgba(226, 196, 126, .32); outline: 1px solid rgba(226, 196, 126, .12); outline-offset: 4px; pointer-events: none; }
            .sha-side > * { position: relative; z-index: 1; }
            .sha-logo img { height: 2.6rem; width: auto; }
            .sha-side-body { flex: 1; display: flex; flex-direction: column; justify-content: center; }
            .sha-eyebrow { font-size: .7rem; font-weight: 600; letter-spacing: .24em; text-transform: uppercase; color: var(--sha-gold-light); margin-bottom: 1rem; }
            .sha-side h1 { font-family: var(--sha-serif); font-size: 2.15rem; line-height: 1.28; font-weight: 400; color: #fff; letter-spacing: -.01em; max-width: 26rem; }
            .sha-orn { display: flex; align-items: center; gap: .6rem; margin: 1.35rem 0 1.2rem; }
            .sha-orn span { width: 2.75rem; height: 1px; background: linear-gradient(90deg, transparent, var(--sha-gold-light)); }
            .sha-orn span:last-child { background: linear-gradient(90deg, var(--sha-gold-light), transparent); }
            .sha-orn i { width: .45rem; height: .45rem; transform: rotate(45deg); background: var(--sha-gold-light); }
            .sha-lead { font-size: 1rem; line-height: 1.65; color: #c3cee4; max-width: 27rem; }
            .sha-points { list-style: none; margin: 2.1rem 0 0; padding: 0; display: flex; flex-direction: column; gap: 1.15rem; }
            .sha-points li { display: flex; gap: 1rem; align-items: flex-start; }
            .sha-points strong { display: block; color: #fff; font-size: .95rem; font-weight: 600; }
            .sha-points span { font-size: .85rem; color: #9fadc8; line-height: 1.5; }
            .sha-ic { flex: none; display: grid; place-items: center; width: 2.5rem; height: 2.5rem; border-radius: 50%; border: 1px solid rgba(226, 196, 126, .55); box-shadow: inset 0 0 0 3px rgba(226, 196, 126, .08); color: var(--sha-gold-light); }
            .sha-ic svg { width: 1.15rem; height: 1.15rem; }

            .sha-side-foot { display: flex; flex-direction: column; gap: 1rem; }
            .sha-help { display: flex; align-items: center; gap: .75rem; padding: .8rem 1rem; border-radius: .5rem; background: rgba(255, 255, 255, .05); border: 1px solid rgba(226, 196, 126, .22); font-size: .85rem; color: #c9d3e6; text-decoration: none; transition: background .15s, border-color .15s; }
            .sha-help:hover { background: rgba(255, 255, 255, .09); border-color: rgba(226, 196, 126, .45); }
            .sha-help strong { color: #fff; font-weight: 600; }
            .sha-help-ic { flex: none; display: grid; place-items: center; width: 2rem; height: 2rem; border-radius: 50%; background: #25d366; color: #fff; }
            .sha-help-ic svg { width: 1.1rem; height: 1.1rem; }

            /* ── Form column: ivory paper ── */
            .sha-main {
                display: flex; flex-direction: column; min-height: 100vh; padding: 1.25rem 2.5rem 1.5rem;
                background: radial-gradient(ellipse at 50% 0%, #fffdf8, transparent 60%), var(--sha-paper);
            }
            .sha-topline { display: flex; align-items: center; justify-content: flex-end; gap: 1rem; }
            .sha-back { display: inline-flex; align-items: center; gap: .4rem; font-size: .85rem; font-weight: 500; color: var(--sha-muted); text-decoration: none; }
            .sha-back:hover { color: var(--sha-ink); }
            .sha-back svg { width: 1rem; height: 1rem; }
            .sha-logo-mobile { display: none; margin-right: auto; }
            .sha-logo-mobile img { height: 2.25rem; width: auto; }

            /* The card: a letterhead rule (navy over gold) on top. margin:auto centres it when short and lets it scroll when tall. */
            .sha-card {
                position: relative; width: 100%; max-width: 28.5rem; margin: auto; background: #fff; border: 1px solid var(--sha-line); border-top: 0; border-radius: 4px 4px 10px 10px;
                padding: 2.5rem 2.5rem 2.25rem; box-shadow: 0 1px 2px rgba(19, 33, 63, .05), 0 24px 48px -28px rgba(19, 33, 63, .35);
            }
            .sha-card::before { content: ""; position: absolute; left: -1px; right: -1px; top: 0; height: 6px; border-radius: 4px 4px 0 0; background: linear-gradient(var(--sha-navy) 0 4px, transparent 4px 5px, var(--sha-gold) 5px 6px); }
            .sha-card-head { text-align: center; margin-bottom: 1.75rem; }
            .sha-seal { display: inline-grid; place-items: center; width: 4rem; height: 4rem; margin-bottom: 1rem; border-radius: 50%; background: #fff; border: 1px solid var(--sha-gold-line); box-shadow: 0 0 0 4px var(--sha-gold-soft), 0 0 0 5px var(--sha-gold-line); }
            .sha-seal img { width: 2.5rem; height: 2.5rem; border-radius: .55rem; }
            .sha-kicker { font-size: .68rem; font-weight: 700; letter-spacing: .24em; text-transform: uppercase; color: var(--sha-gold); margin-bottom: .45rem; }
            .sha-h { font-family: var(--sha-serif); font-size: 1.7rem; font-weight: 700; color: var(--sha-ink); line-height: 1.25; letter-spacing: -.01em; }
            .sha-sub { margin-top: .55rem; color: var(--sha-muted); font-size: .92rem; line-height: 1.55; }

            /* The wide (registration) card is sized to fit one screen on a laptop: three fields a row, tight rhythm. */
            .sha-card-wide { max-width: 54rem; padding: 1.75rem 2.5rem 1.25rem; }
            .sha-card-wide .sha-card-head { text-align: left; margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid var(--sha-soft); }
            .sha-card-wide .sha-h { font-size: 1.5rem; }
            .sha-card-wide .sha-sub { margin-top: .3rem; }
            .sha-card-wide .fi-sc-has-gap { row-gap: .9rem; }
            .sha-card-wide .fi-section-header { padding-bottom: .75rem; }
            .sha-card-wide .fi-sc-component + .fi-sc-component:has(> .fi-sc-section) { margin-top: .25rem; padding-top: 1rem; }
            .sha-card-wide .fi-form-actions, .sha-card-wide .fi-ac { margin-top: .25rem; }
            .sha-card-wide .sha-alt { margin-top: 1rem; padding-top: .9rem; }

            /* Links and notes under the form. */
            .sha-alt { margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--sha-soft); text-align: center; font-size: .9rem; color: var(--sha-muted); }
            .sha-alt a { color: var(--primary-600); font-weight: 600; text-decoration: none; }
            .sha-alt a:hover { text-decoration: underline; text-decoration-color: var(--sha-gold); text-underline-offset: 3px; }
            .sha-or { display: flex; align-items: center; gap: .85rem; margin: 1.6rem 0 1rem; font-size: .78rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--sha-muted); }
            .sha-or::before, .sha-or::after { content: ""; flex: 1; height: 1px; background: var(--sha-line); }
            .sha-btn-outline { display: flex; align-items: center; justify-content: center; gap: .5rem; min-height: 2.875rem; padding: .55rem 1rem; border-radius: 6px; border: 1px solid #c9d2e3; background: #fff; color: var(--primary-600); font-size: .9rem; font-weight: 600; text-align: center; text-decoration: none; transition: border-color .15s, background .15s; }
            .sha-btn-outline:hover { border-color: var(--primary-600); background: var(--primary-50); }
            .sha-btn-outline svg { width: 1rem; height: 1rem; flex: none; }
            .sha-note { display: flex; gap: .75rem; align-items: flex-start; margin-top: 1.25rem; padding: .85rem 1rem; border-radius: 4px; background: var(--sha-gold-soft); border: 1px solid var(--sha-gold-line); border-left: 3px solid var(--sha-gold); color: #5c4718; font-size: .84rem; line-height: 1.5; }
            .sha-note strong { color: #3f300c; }
            .sha-note svg { flex: none; width: 1.2rem; height: 1.2rem; margin-top: .05rem; color: var(--sha-gold); }

            /* Under the card. */
            .sha-foot { display: flex; flex-wrap: wrap; justify-content: center; gap: .35rem 1rem; margin-top: 1.5rem; font-size: .76rem; color: #8a8f9c; text-align: center; }
            .sha-foot a { color: #6b7385; text-decoration: underline; text-underline-offset: 2px; }
            .sha-foot a:hover { color: var(--sha-ink); }
            .sha-foot-help { display: none; }

            /* ── Filament controls inside the card ── */
            .sha .fi-fo-field-label-content { font-weight: 600; color: var(--sha-ink); }
            .sha .fi-input-wrp { border-radius: 6px; background: #fff; }
            .sha .fi-input-wrp:not(:focus-within) { box-shadow: 0 0 0 1px #d6d9e0, 0 1px 2px rgba(19, 33, 63, .04); }
            .sha .fi-input { padding-top: .65rem; padding-bottom: .65rem; }
            .sha .fi-btn { min-height: 2.9rem; font-weight: 600; letter-spacing: .01em; border-radius: 6px; }
            .sha .fi-btn.fi-color-primary:not(.fi-outlined):not(.fi-link) { background: linear-gradient(180deg, #23457f, var(--primary-700)); box-shadow: 0 1px 0 rgba(255, 255, 255, .12) inset, 0 6px 14px -8px rgba(15, 33, 66, .7); }
            .sha .fi-btn.fi-color-primary:not(.fi-outlined):not(.fi-link):hover { background: linear-gradient(180deg, #1c3a74, var(--primary-800)); }
            .sha .fi-checkbox-input { border-radius: 4px; }

            /* Form sections read as numbered steps, not boxes inside a box. */
            .sha-card { counter-reset: sha-step; }
            /* !important: the panel stylesheet (filament-custom.css) boxes every .fi-section with !important. */
            .sha .fi-section { counter-increment: sha-step; background: none !important; box-shadow: none !important; border: 0 !important; border-radius: 0 !important; padding: 0; }
            /* Each section sits in its own .fi-sc-component wrapper, so separate the wrappers. */
            .sha .fi-sc-component + .fi-sc-component:has(> .fi-sc-section) { padding-top: .75rem; border-top: 1px solid var(--sha-soft); }
            .sha .fi-section-header { padding: 0 0 1rem; border: 0; }
            .sha .fi-section-header-text-ctn { position: relative; padding-left: 2.6rem; }
            .sha .fi-section-header-text-ctn::before { content: counter(sha-step); position: absolute; left: 0; top: .05rem; display: grid; place-items: center; width: 1.8rem; height: 1.8rem; border-radius: 50%; background: var(--primary-600); box-shadow: 0 0 0 2px #fff, 0 0 0 3px var(--sha-gold-line); color: #fff; font-family: var(--sha-serif); font-size: .8rem; font-weight: 700; }
            .sha .fi-section-header-heading { font-family: var(--sha-serif); font-size: 1rem; font-weight: 700; color: var(--sha-ink); }
            .sha .fi-section-header-description { margin-top: .15rem; font-size: .83rem; color: var(--sha-muted); }
            .sha .fi-section-content-ctn { border: 0; }
            .sha .fi-section-content { padding: 0; }

            /* Short laptop screens: keep everything on one screen. */
            @media (max-height: 780px) {
                .sha-side { padding-top: 2rem; padding-bottom: 2rem; gap: 1.25rem; }
                .sha-side h1 { font-size: 1.75rem; }
                .sha-points { margin-top: 1.5rem; gap: .9rem; }
                .sha-side .shr-next { display: none; }
                .sha-main { padding-top: .75rem; }
                .sha-card { padding-top: 1.75rem; padding-bottom: 1.75rem; }
                .sha-card-head { margin-bottom: 1.25rem; }
                .sha-seal { display: none; }
                .sha-note { margin-top: 1rem; }
                .sha-or { margin: 1.1rem 0 .75rem; }
                .sha-foot { margin-top: 1rem; }
            }

            @media (max-width: 1100px) {
                .sha { grid-template-columns: minmax(20rem, 26rem) 1fr; }
                .sha-side { padding: 2.5rem 2.25rem; }
                .sha-side h1 { font-size: 1.75rem; }
            }

            /* Tablets and phones: a navy band behind the top of the card instead of the panel. */
            @media (max-width: 900px) {
                .sha { grid-template-columns: 1fr; }
                .sha-side { display: none; }
                .sha-note-parents { display: none; }
                .sha-main {
                    padding: 1rem 1rem 1.5rem;
                    background:
                        linear-gradient(var(--sha-gold) 0 0) 0 11rem / 100% 2px no-repeat,
                        linear-gradient(170deg, #17336e, var(--sha-navy) 55%, var(--sha-navy-deep)) 0 0 / 100% 11rem no-repeat,
                        var(--sha-paper);
                }
                .sha-logo-mobile { display: inline-block; }
                .sha-back { color: #c9d3e6; }
                .sha-back:hover { color: #fff; }
                .sha-card { margin: 1.75rem auto auto; padding: 2rem 1.35rem 1.75rem; }
                .sha-card-wide { padding: 1.5rem 1.25rem 1.25rem; }
                .sha-foot-help { display: inline; }
            }
        </style>
    @endonce

    <aside class="sha-side">
        <a href="{{ url('/') }}" class="sha-logo"><img src="{{ asset('images/schoolhub-logo-sidebar.svg') }}" alt="SchoolHub"></a>

        <div class="sha-side-body">
            @if (isset($side) && $side->isNotEmpty())
                {{ $side }}
            @else
                <p class="sha-eyebrow">School management for Uganda</p>
                <h1>Run your whole school from one place.</h1>
                <div class="sha-orn" aria-hidden="true"><span></span><i></i><span></span></div>
                <p class="sha-lead">Fees, exams and report cards, payroll and finance, built for Ugandan primary and secondary schools.</p>

                <ul class="sha-points">
                    <li>
                        <span class="sha-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z"/></svg></span>
                        <div><strong>Fees &amp; receipts</strong><span>Billing, balances, receipts and reminder letters.</span></div>
                    </li>
                    <li>
                        <span class="sha-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342"/></svg></span>
                        <div><strong>Exams &amp; report cards</strong><span>PLE aggregates, the new O-Level curriculum and A-Level points.</span></div>
                    </li>
                    <li>
                        <span class="sha-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg></span>
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
        </div>
    </aside>

    <main class="sha-main">
        <div class="sha-topline">
            <a href="{{ url('/') }}" class="sha-logo-mobile"><img src="{{ asset('images/schoolhub-logo-sidebar.svg') }}" alt="SchoolHub"></a>
            <a href="{{ url('/') }}" class="sha-back">
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.612l4.158 3.96a.75.75 0 1 1-1.04 1.08l-5.5-5.25a.75.75 0 0 1 0-1.08l5.5-5.25a.75.75 0 1 1 1.04 1.08L5.612 9.25H16.25A.75.75 0 0 1 17 10Z" clip-rule="evenodd"/></svg>
                Back to home
            </a>
        </div>

        <div @class(['sha-card', 'sha-card-wide' => $wide])>
            <header class="sha-card-head">
                @unless ($wide)
                    <span class="sha-seal" aria-hidden="true"><img src="{{ asset('images/schoolhub-icon.svg') }}" alt=""></span>
                @endunless
                @if ($eyebrow)<p class="sha-kicker">{{ $eyebrow }}</p>@endif
                <h2 class="sha-h">{{ $heading }}</h2>
                @if ($subheading)<p class="sha-sub">{{ $subheading }}</p>@endif
            </header>

            {{ $slot }}
        </div>

        <footer class="sha-foot">
            <a href="{{ $waUrl }}" class="sha-foot-help" target="_blank" rel="noopener">Need help? WhatsApp or call {{ $contact['phone'] }}</a>
            <span>{{ $copyright }} · <a href="{{ route('filament.app.legal.terms') }}" target="_blank" rel="noopener">Terms</a></span>
        </footer>
    </main>
</div>
