{{--
    SchoolHub public landing page (site root). Self-contained: its own
    styles and a few lines of script, no Filament chrome or build step.
    Served by LandingController.

    Palette: slate neutrals + one blue primary (the app panel's
    Color::Blue), with the logo's amber used only as a small accent.
--}}
@php
    $signedIn = filament()->auth()->check();
    $loginUrl = filament()->getLoginUrl();
    $registerUrl = filament()->getRegistrationUrl();
    $dashboardUrl = filament()->getUrl();
    $startUrl = $signedIn ? $dashboardUrl : $registerUrl;
    $graceDays = (int) config('subscriptions.grace_days', 14);
    $telUrl = 'tel:+256' . ltrim(preg_replace('/\D/', '', $contact['phone']), '0');
    $waUrl = 'https://wa.me/' . $contact['whatsapp'] . '?text=' . rawurlencode("Hello, I'd like to book a demo of SchoolHub for my school.");
    $mailUrl = 'mailto:' . $contact['email'] . '?subject=' . rawurlencode('SchoolHub demo request');
    $demoErrors = $errors->getBag('demo');
    $demoSent = session('demo_sent');
    $hasYearly = $plans->contains(fn ($p) => $p->price_per_year > 0);
    $popular = $plans->count() >= 3 ? $plans->values()[1]->id : null;
    $check = '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>';
    $arrow = '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd"/></svg>';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SchoolHub — School management software for Ugandan schools</title>
    <meta name="description" content="Students, fees, exams and report cards, payroll and finance for Ugandan primary and secondary schools. Register your school and try every module free for {{ $trialDays }} days.">
    <meta name="theme-color" content="#ffffff">
    <meta property="og:title" content="SchoolHub — School management software for Ugandan schools">
    <meta property="og:description" content="Fees, exams, report cards, payroll and finance in one system. Free {{ $trialDays }}-day trial.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/') }}">
    <link rel="icon" href="{{ asset('images/schoolhub-icon-192.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Neutrals (slate) */
            --slate-900: #0f172a; --slate-800: #1e293b; --slate-700: #334155; --slate-600: #475569;
            --slate-500: #64748b; --slate-400: #94a3b8; --slate-300: #cbd5e1; --slate-200: #e2e8f0;
            --slate-100: #f1f5f9; --slate-50: #f8fafc;
            /* Primary (blue) */
            --blue-700: #1d4ed8; --blue-600: #2563eb; --blue-500: #3b82f6; --blue-100: #dbeafe; --blue-50: #eff6ff;
            /* Status */
            --green-600: #16a34a; --green-50: #f0fdf4; --amber-500: #f59e0b; --amber-50: #fffbeb; --red-600: #dc2626;

            --radius: .75rem; --radius-lg: 1rem;
            --shadow-sm: 0 1px 2px rgba(15, 23, 42, .06);
            --shadow: 0 1px 3px rgba(15, 23, 42, .08), 0 1px 2px rgba(15, 23, 42, .04);
            --shadow-lg: 0 24px 48px -16px rgba(15, 23, 42, .18);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; scroll-padding-top: 5rem; }
        body { font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: var(--slate-700); background: #fff; line-height: 1.6; font-size: 1rem; -webkit-font-smoothing: antialiased; text-rendering: optimizeLegibility; }
        a { color: inherit; text-decoration: none; }
        img, svg { display: block; max-width: 100%; }
        :focus-visible { outline: 2px solid var(--blue-600); outline-offset: 2px; border-radius: .375rem; }
        .container { width: 100%; max-width: 76rem; margin: 0 auto; padding: 0 1.5rem; }
        .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }

        h1, h2, h3 { color: var(--slate-900); letter-spacing: -.02em; line-height: 1.2; }

        /* ── Buttons ── */
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; height: 2.75rem; padding: 0 1.25rem; border-radius: .5rem; font-size: .9375rem; font-weight: 600; line-height: 1; border: 1px solid transparent; cursor: pointer; transition: background-color .15s, border-color .15s, color .15s, box-shadow .15s; white-space: nowrap; }
        .btn svg { width: 1.125rem; height: 1.125rem; }
        .btn-lg { height: 3.25rem; padding: 0 1.75rem; font-size: 1rem; }
        .btn-sm { height: 2.375rem; padding: 0 1rem; font-size: .875rem; }
        .btn-primary { background: var(--blue-600); color: #fff; box-shadow: var(--shadow-sm); }
        .btn-primary:hover { background: var(--blue-700); }
        .btn-secondary { background: #fff; color: var(--slate-900); border-color: var(--slate-300); box-shadow: var(--shadow-sm); }
        .btn-secondary:hover { background: var(--slate-50); border-color: var(--slate-400); }
        .btn-white { background: #fff; color: var(--blue-700); }
        .btn-white:hover { background: var(--blue-50); }
        .btn-outline-white { color: #fff; border-color: rgba(255, 255, 255, .4); }
        .btn-outline-white:hover { background: rgba(255, 255, 255, .1); }
        .btn-block { width: 100%; }

        /* ── Header ── */
        .header { position: sticky; top: 0; z-index: 50; background: rgba(255, 255, 255, .85); backdrop-filter: saturate(180%) blur(12px); -webkit-backdrop-filter: saturate(180%) blur(12px); border-bottom: 1px solid transparent; transition: border-color .2s, box-shadow .2s; }
        .header.is-scrolled { border-bottom-color: var(--slate-200); box-shadow: var(--shadow-sm); }
        .header-inner { display: flex; align-items: center; justify-content: space-between; height: 4.25rem; gap: 2rem; }
        .brand img { height: 2.75rem; width: auto; }
        .nav { display: flex; gap: 2rem; font-size: .9375rem; font-weight: 500; color: var(--slate-600); }
        .nav a:hover { color: var(--slate-900); }
        .header-actions { display: flex; align-items: center; gap: .75rem; }
        .link-signin { font-size: .9375rem; font-weight: 600; color: var(--slate-700); padding: 0 .5rem; }
        .link-signin:hover { color: var(--slate-900); }
        .menu-btn { display: none; width: 2.5rem; height: 2.5rem; align-items: center; justify-content: center; border: 1px solid var(--slate-200); border-radius: .5rem; background: #fff; color: var(--slate-700); cursor: pointer; }
        .menu-btn svg { width: 1.25rem; height: 1.25rem; }
        .mobile-nav { display: none; border-top: 1px solid var(--slate-200); background: #fff; padding: 1rem 1.5rem 1.5rem; }
        .mobile-nav a:not(.btn) { display: block; padding: .75rem 0; font-weight: 500; color: var(--slate-700); border-bottom: 1px solid var(--slate-100); }
        .mobile-nav .btn { margin-top: .75rem; }
        .mobile-nav .btn:first-of-type { margin-top: 1.25rem; }
        .header.is-open .mobile-nav { display: block; }

        /* ── Hero ── */
        .hero { position: relative; padding: 2.5rem 0 4rem; overflow: hidden; }
        .hero::before { content: ""; position: absolute; inset: 0; z-index: -1; background-image: linear-gradient(var(--slate-100) 1px, transparent 1px), linear-gradient(90deg, var(--slate-100) 1px, transparent 1px); background-size: 3.5rem 3.5rem; mask-image: radial-gradient(ellipse 70% 60% at 50% 0%, #000 40%, transparent 100%); -webkit-mask-image: radial-gradient(ellipse 70% 60% at 50% 0%, #000 40%, transparent 100%); }
        .hero-copy { max-width: 48rem; margin: 0 auto; text-align: center; }
        .badge { display: inline-flex; align-items: center; gap: .5rem; padding: .3125rem .875rem .3125rem .375rem; border: 1px solid var(--slate-200); border-radius: 999px; background: #fff; font-size: .8125rem; font-weight: 500; color: var(--slate-600); box-shadow: var(--shadow-sm); }
        .badge b { padding: .125rem .5rem; border-radius: 999px; background: var(--blue-50); color: var(--blue-700); font-weight: 600; font-size: .75rem; }
        .hero h1 { margin-top: 1.5rem; font-size: clamp(2.375rem, 5.5vw, 3.75rem); font-weight: 800; letter-spacing: -.035em; line-height: 1.08; }
        .hero h1 span { color: var(--blue-600); }
        .hero-lead { margin: 1.5rem auto 0; max-width: 40rem; font-size: 1.1875rem; color: var(--slate-600); }
        .hero-actions { margin-top: 2.25rem; display: flex; flex-wrap: wrap; justify-content: center; gap: .875rem; }
        .hero-points { margin-top: 1.75rem; display: flex; flex-wrap: wrap; justify-content: center; gap: .5rem 1.75rem; font-size: .875rem; color: var(--slate-500); }
        .hero-points span { display: inline-flex; align-items: center; gap: .375rem; }
        .hero-points svg { width: 1rem; height: 1rem; color: var(--green-600); }

        /* ── Product preview (pure HTML/CSS, never goes stale like a screenshot) ── */
        .preview { position: relative; margin: 4rem auto 0; max-width: 64rem; }
        .preview::before { content: ""; position: absolute; inset: 8% 10% -6%; z-index: -1; background: radial-gradient(closest-side, rgba(37, 99, 235, .18), transparent); filter: blur(24px); }
        .window { border: 1px solid var(--slate-200); border-radius: var(--radius-lg); background: #fff; box-shadow: var(--shadow-lg); overflow: hidden; }
        .window-bar { display: flex; align-items: center; gap: .75rem; height: 2.5rem; padding: 0 1rem; border-bottom: 1px solid var(--slate-200); background: var(--slate-50); }
        .dots { display: flex; gap: .375rem; }
        .dots i { width: .625rem; height: .625rem; border-radius: 50%; background: var(--slate-300); }
        .url { flex: 1; max-width: 20rem; margin: 0 auto; height: 1.5rem; border-radius: .375rem; background: #fff; border: 1px solid var(--slate-200); font-size: .6875rem; color: var(--slate-400); display: flex; align-items: center; justify-content: center; }
        .app { display: grid; grid-template-columns: 12.5rem 1fr; min-height: 25rem; text-align: left; }
        .app-side { border-right: 1px solid var(--slate-200); padding: 1rem .75rem; background: #fff; }
        .app-side img { height: 1.75rem; width: auto; margin: 0 .5rem 1.25rem; }
        .app-side p { margin: 1rem .5rem .375rem; font-size: .625rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--slate-400); }
        .app-side a { display: flex; align-items: center; gap: .5rem; padding: .4375rem .5rem; border-radius: .375rem; font-size: .75rem; font-weight: 500; color: var(--slate-600); }
        .app-side a i { width: .875rem; height: .875rem; border-radius: .25rem; background: var(--slate-200); }
        .app-side a.on { background: var(--blue-50); color: var(--blue-700); }
        .app-side a.on i { background: var(--blue-600); }
        .app-main { padding: 1.25rem; background: var(--slate-50); }
        .app-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .app-head strong { font-size: .9375rem; color: var(--slate-900); }
        .app-head span { font-size: .6875rem; color: var(--slate-500); }
        .chip { padding: .3125rem .625rem; border-radius: .375rem; background: var(--blue-600); color: #fff; font-size: .6875rem; font-weight: 600; }
        .kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: .75rem; }
        .kpi { padding: .875rem; border: 1px solid var(--slate-200); border-radius: .625rem; background: #fff; }
        .kpi small { display: block; font-size: .625rem; font-weight: 500; color: var(--slate-500); }
        .kpi b { display: block; margin-top: .25rem; font-size: 1.125rem; font-weight: 700; color: var(--slate-900); letter-spacing: -.01em; }
        .kpi em { font-style: normal; font-size: .625rem; font-weight: 600; color: var(--green-600); }
        .kpi em.warn { color: var(--amber-500); }
        .app-row { margin-top: .75rem; display: grid; grid-template-columns: 1.6fr 1fr; gap: .75rem; }
        .panel { padding: .875rem; border: 1px solid var(--slate-200); border-radius: .625rem; background: #fff; }
        .panel-title { font-size: .6875rem; font-weight: 600; color: var(--slate-900); }
        .chart { margin-top: .75rem; height: 8.5rem; display: flex; align-items: flex-end; gap: .5rem; border-bottom: 1px solid var(--slate-200); }
        .chart i { flex: 1; border-radius: .25rem .25rem 0 0; background: var(--blue-500); }
        .chart i.muted { background: var(--blue-100); }
        .list { margin-top: .5rem; }
        .list div { display: flex; justify-content: space-between; align-items: center; gap: .5rem; padding: .5rem 0; border-bottom: 1px solid var(--slate-100); font-size: .6875rem; }
        .list div:last-child { border-bottom: 0; }
        .list b { font-weight: 600; color: var(--slate-800); }
        .list span { color: var(--slate-500); }
        .tag { padding: .125rem .4375rem; border-radius: 999px; font-size: .625rem; font-weight: 600; white-space: nowrap; }
        .tag-green { background: var(--green-50); color: var(--green-600); }
        .tag-amber { background: var(--amber-50); color: #b45309; }

        /* ── Facts strip ── */
        .facts { border-top: 1px solid var(--slate-200); border-bottom: 1px solid var(--slate-200); background: var(--slate-50); }
        .facts-grid { display: grid; grid-template-columns: repeat(4, 1fr); }
        .fact { padding: 2rem 1.5rem; text-align: center; }
        .fact + .fact { border-left: 1px solid var(--slate-200); }
        .fact b { display: block; font-size: 1.875rem; font-weight: 800; color: var(--slate-900); letter-spacing: -.02em; }
        .fact span { font-size: .875rem; color: var(--slate-500); }

        /* ── Sections ── */
        .section { padding: 6rem 0; }
        .section-alt { background: var(--slate-50); }
        .section-head { max-width: 42rem; margin: 0 auto 3.5rem; text-align: center; }
        .eyebrow { font-size: .875rem; font-weight: 600; color: var(--blue-600); }
        .section-head h2 { margin-top: .5rem; font-size: clamp(1.875rem, 3.5vw, 2.5rem); font-weight: 800; letter-spacing: -.03em; }
        .section-head p { margin-top: 1rem; font-size: 1.0625rem; color: var(--slate-600); }

        /* Feature grid */
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
        .card { padding: 1.75rem; border: 1px solid var(--slate-200); border-radius: var(--radius-lg); background: #fff; box-shadow: var(--shadow-sm); transition: border-color .2s, box-shadow .2s; }
        .card:hover { border-color: var(--slate-300); box-shadow: var(--shadow); }
        .icon { display: flex; align-items: center; justify-content: center; width: 2.75rem; height: 2.75rem; border-radius: .625rem; background: var(--blue-50); color: var(--blue-600); border: 1px solid var(--blue-100); }
        .icon svg { width: 1.375rem; height: 1.375rem; }
        .card h3 { margin-top: 1.25rem; font-size: 1.0625rem; font-weight: 700; }
        .card p { margin-top: .5rem; font-size: .9375rem; color: var(--slate-600); }

        /* Showcase rows */
        .showcase { display: grid; gap: 6rem; }
        .show { display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center; }
        .show:nth-child(even) .show-media { order: -1; }
        .show h3 { margin-top: .5rem; font-size: clamp(1.5rem, 2.6vw, 2rem); font-weight: 800; letter-spacing: -.025em; }
        .show-copy > p { margin-top: 1rem; font-size: 1.0625rem; color: var(--slate-600); }
        .ticks { list-style: none; margin-top: 1.5rem; display: grid; gap: .75rem; }
        .ticks li { display: flex; gap: .75rem; font-size: .9375rem; color: var(--slate-700); }
        .ticks svg { flex: none; width: 1.25rem; height: 1.25rem; margin-top: .125rem; color: var(--blue-600); }
        .show-media { padding: 1.5rem; border: 1px solid var(--slate-200); border-radius: var(--radius-lg); background: linear-gradient(180deg, var(--slate-50), #fff); box-shadow: var(--shadow); }
        .doc { border: 1px solid var(--slate-200); border-radius: .75rem; background: #fff; padding: 1.25rem; box-shadow: var(--shadow-sm); font-size: .8125rem; }
        .doc-head { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: .875rem; border-bottom: 1px dashed var(--slate-200); }
        .doc-head strong { display: block; color: var(--slate-900); font-size: .9375rem; }
        .doc-head span { color: var(--slate-500); font-size: .75rem; }
        .doc-rows { margin-top: .75rem; display: grid; gap: .5rem; }
        .doc-rows div { display: flex; justify-content: space-between; color: var(--slate-600); }
        .doc-rows div b { color: var(--slate-900); font-weight: 600; }
        .doc-total { margin-top: .875rem; padding-top: .875rem; border-top: 1px solid var(--slate-200); display: flex; justify-content: space-between; font-weight: 700; color: var(--slate-900); }
        .table { width: 100%; border-collapse: collapse; font-size: .8125rem; }
        .table th { text-align: left; font-size: .6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--slate-500); padding: .5rem .625rem; background: var(--slate-50); border-bottom: 1px solid var(--slate-200); }
        .table td { padding: .625rem; border-bottom: 1px solid var(--slate-100); color: var(--slate-700); }
        .table td b { color: var(--slate-900); font-weight: 600; }
        .table tr:last-child td { border-bottom: 0; }
        .num { text-align: right !important; font-variant-numeric: tabular-nums; }

        /* Steps */
        .steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; counter-reset: step; }
        .step { position: relative; padding: 1.75rem; border-radius: var(--radius-lg); background: #fff; border: 1px solid var(--slate-200); box-shadow: var(--shadow-sm); }
        .step-num { display: inline-flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; border-radius: 50%; background: var(--blue-600); color: #fff; font-weight: 700; font-size: .9375rem; }
        .step h3 { margin-top: 1.25rem; font-size: 1.0625rem; font-weight: 700; }
        .step p { margin-top: .5rem; font-size: .9375rem; color: var(--slate-600); }

        /* Pricing */
        .toggle { display: inline-flex; margin: 0 auto 3rem; padding: .25rem; border-radius: .625rem; background: var(--slate-100); border: 1px solid var(--slate-200); }
        .toggle-wrap { text-align: center; }
        .toggle button { height: 2.25rem; padding: 0 1.125rem; border: 0; border-radius: .5rem; background: transparent; font: inherit; font-size: .875rem; font-weight: 600; color: var(--slate-600); cursor: pointer; }
        .toggle button[aria-pressed="true"] { background: #fff; color: var(--slate-900); box-shadow: var(--shadow); }
        .toggle small { margin-left: .375rem; color: var(--green-600); font-weight: 600; }
        .plans { display: grid; grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr)); gap: 1.5rem; align-items: stretch; }
        .plan { position: relative; display: flex; flex-direction: column; padding: 2rem; border: 1px solid var(--slate-200); border-radius: var(--radius-lg); background: #fff; box-shadow: var(--shadow-sm); }
        .plan.featured { border: 2px solid var(--blue-600); box-shadow: var(--shadow-lg); }
        .plan-flag { position: absolute; top: -.8125rem; left: 50%; transform: translateX(-50%); padding: .25rem .75rem; border-radius: 999px; background: var(--blue-600); color: #fff; font-size: .75rem; font-weight: 600; white-space: nowrap; }
        .plan h3 { font-size: 1.125rem; font-weight: 700; }
        .plan-desc { margin-top: .375rem; font-size: .875rem; color: var(--slate-500); min-height: 1.5rem; }
        .plan-price { margin-top: 1.5rem; display: flex; flex-wrap: wrap; align-items: baseline; gap: .25rem .375rem; }
        .plan-price small { font-size: .875rem; font-weight: 600; color: var(--slate-500); }
        .plan-price b { font-size: clamp(1.625rem, 2.2vw, 2rem); font-weight: 800; color: var(--slate-900); letter-spacing: -.03em; line-height: 1; }
        .plan-price span { flex-basis: 100%; font-size: .875rem; color: var(--slate-500); }
        .plan-sub { margin-top: .5rem; font-size: .8125rem; color: var(--slate-500); min-height: 1.25rem; }
        .plan .btn { margin-top: 1.75rem; }
        .plan ul { list-style: none; margin-top: 1.75rem; padding-top: 1.75rem; border-top: 1px solid var(--slate-200); display: grid; gap: .75rem; font-size: .9375rem; }
        .plan li { display: flex; gap: .625rem; }
        .plan li svg { flex: none; width: 1.25rem; height: 1.25rem; color: var(--blue-600); }
        .pricing-note { margin-top: 2rem; text-align: center; font-size: .9375rem; color: var(--slate-500); }
        [data-cycle="year"] .price-term, [data-cycle="term"] .price-year { display: none; }

        /* FAQ */
        .faq { max-width: 48rem; margin: 0 auto; border-top: 1px solid var(--slate-200); }
        .faq details { border-bottom: 1px solid var(--slate-200); }
        .faq summary { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 1.375rem 0; font-size: 1.0625rem; font-weight: 600; color: var(--slate-900); cursor: pointer; list-style: none; }
        .faq summary::-webkit-details-marker { display: none; }
        .faq summary svg { flex: none; width: 1.25rem; height: 1.25rem; color: var(--slate-400); transition: transform .2s; }
        .faq details[open] summary svg { transform: rotate(45deg); color: var(--blue-600); }
        .faq details p { padding: 0 2.5rem 1.5rem 0; color: var(--slate-600); }

        /* Demo booking */
        .demo { display: grid; grid-template-columns: 1fr 1.25fr; gap: 3rem; align-items: start; }
        .demo-intro h2 { margin-top: .5rem; font-size: clamp(1.875rem, 3.5vw, 2.5rem); font-weight: 800; letter-spacing: -.03em; }
        .demo-intro > p { margin-top: 1rem; font-size: 1.0625rem; color: var(--slate-600); }
        .contact-list { margin-top: 2rem; display: grid; gap: .875rem; }
        .contact-item { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.125rem; border: 1px solid var(--slate-200); border-radius: var(--radius); background: #fff; box-shadow: var(--shadow-sm); transition: border-color .15s, box-shadow .15s; }
        .contact-item:hover { border-color: var(--slate-300); box-shadow: var(--shadow); }
        .contact-item .icon { flex: none; }
        .contact-item.wa .icon { background: var(--green-50); border-color: #bbf7d0; color: var(--green-600); }
        .contact-item small { display: block; font-size: .8125rem; color: var(--slate-500); }
        .contact-item b { display: block; font-size: 1rem; font-weight: 600; color: var(--slate-900); word-break: break-word; }
        .contact-item .go { margin-left: auto; width: 1.125rem; height: 1.125rem; color: var(--slate-400); flex: none; }
        .expect { list-style: none; margin-top: 2rem; display: grid; gap: .625rem; font-size: .9375rem; color: var(--slate-600); }
        .expect li { display: flex; gap: .625rem; }
        .expect svg { flex: none; width: 1.25rem; height: 1.25rem; margin-top: .125rem; color: var(--blue-600); }

        .form-card { padding: 2rem; border: 1px solid var(--slate-200); border-radius: var(--radius-lg); background: #fff; box-shadow: var(--shadow-lg); }
        .form-card h3 { font-size: 1.25rem; font-weight: 700; }
        .form-card > p { margin-top: .25rem; font-size: .9375rem; color: var(--slate-500); }
        .form-grid { margin-top: 1.5rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem 1.25rem; }
        .field { display: flex; flex-direction: column; gap: .375rem; }
        .field.full { grid-column: 1 / -1; }
        .field label, .field .field-label { font-size: .875rem; font-weight: 600; color: var(--slate-800); }
        .field label i { font-style: normal; font-weight: 400; color: var(--slate-400); }
        .field input, .field select, .field textarea { width: 100%; height: 2.75rem; padding: 0 .875rem; border: 1px solid var(--slate-300); border-radius: .5rem; background: #fff; font: inherit; font-size: .9375rem; color: var(--slate-900); box-shadow: var(--shadow-sm); transition: border-color .15s, box-shadow .15s; }
        .field textarea { height: auto; min-height: 6rem; padding: .75rem .875rem; resize: vertical; }
        .field input::placeholder, .field textarea::placeholder { color: var(--slate-400); }
        .field input:focus, .field select:focus, .field textarea:focus { outline: none; border-color: var(--blue-600); box-shadow: 0 0 0 3px rgba(37, 99, 235, .15); }
        .field.has-error input, .field.has-error select, .field.has-error textarea { border-color: var(--red-600); }
        .field .err { font-size: .8125rem; color: var(--red-600); }
        .radios { display: flex; flex-wrap: wrap; gap: .5rem; }
        .radios label { position: relative; display: inline-flex; align-items: center; height: 2.5rem; padding: 0 1rem; border: 1px solid var(--slate-300); border-radius: .5rem; font-weight: 500; color: var(--slate-700); cursor: pointer; }
        .radios input { position: absolute; opacity: 0; width: 1px; height: 1px; }
        .radios label:has(input:checked) { border-color: var(--blue-600); background: var(--blue-50); color: var(--blue-700); box-shadow: 0 0 0 1px var(--blue-600) inset; }
        .radios label:has(input:focus-visible) { outline: 2px solid var(--blue-600); outline-offset: 2px; }
        .hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
        .form-foot { margin-top: 1.5rem; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; }
        .form-foot small { font-size: .8125rem; color: var(--slate-500); }
        .alert { margin-top: 1.25rem; padding: .875rem 1rem; border-radius: .625rem; font-size: .9375rem; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .success { padding: 1rem 0 .5rem; text-align: center; }
        .success .icon { margin: 0 auto; width: 3.5rem; height: 3.5rem; border-radius: 50%; background: var(--green-50); border-color: #bbf7d0; color: var(--green-600); }
        .success .icon svg { width: 1.75rem; height: 1.75rem; }
        .success h3 { margin-top: 1.25rem; }
        .success p { margin: .5rem auto 0; max-width: 26rem; color: var(--slate-600); }
        .success .btn { margin-top: 1.5rem; }

        /* Floating WhatsApp button */
        .wa-float { position: fixed; right: 1.25rem; bottom: 1.25rem; z-index: 60; display: flex; align-items: center; gap: .5rem; height: 3.25rem; padding: 0 1.125rem 0 .875rem; border-radius: 999px; background: #25d366; color: #fff; font-weight: 600; font-size: .9375rem; box-shadow: 0 10px 25px -8px rgba(22, 163, 74, .6); transition: transform .15s, box-shadow .15s; }
        .wa-float:hover { transform: translateY(-2px); box-shadow: 0 14px 28px -8px rgba(22, 163, 74, .7); }
        .wa-float svg { width: 1.625rem; height: 1.625rem; }

        /* CTA */
        .cta { position: relative; overflow: hidden; padding: 4rem 2rem; border-radius: 1.25rem; background: var(--blue-600); text-align: center; color: #fff; }
        .cta::before { content: ""; position: absolute; inset: 0; background-image: linear-gradient(rgba(255, 255, 255, .07) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, .07) 1px, transparent 1px); background-size: 2.5rem 2.5rem; mask-image: radial-gradient(ellipse at center, #000, transparent 75%); -webkit-mask-image: radial-gradient(ellipse at center, #000, transparent 75%); }
        .cta > * { position: relative; }
        .cta h2 { color: #fff; font-size: clamp(1.75rem, 3.5vw, 2.5rem); font-weight: 800; letter-spacing: -.03em; }
        .cta p { margin: 1rem auto 0; max-width: 36rem; font-size: 1.0625rem; color: var(--blue-100); }
        .cta .hero-actions { margin-top: 2rem; }

        /* Footer */
        .footer { margin-top: 6rem; background: var(--slate-900); color: var(--slate-400); }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 3rem; padding: 4rem 0 3rem; }
        .footer img { height: 2.75rem; width: auto; }
        .footer { padding-bottom: 4.5rem; }   /* room for the floating WhatsApp button */
        .footer-about { margin-top: 1rem; max-width: 20rem; font-size: .9375rem; }
        .footer h4 { font-size: .875rem; font-weight: 600; color: #fff; }
        .footer ul { list-style: none; margin-top: 1rem; display: grid; gap: .625rem; font-size: .9375rem; }
        .footer ul a:hover { color: #fff; }
        .footer-bottom { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem; padding: 1.5rem 0; border-top: 1px solid var(--slate-800); font-size: .875rem; }

        /* ── Responsive ── */
        @media (max-width: 1024px) {
            .demo { grid-template-columns: 1fr; }
            .grid-3, .steps { grid-template-columns: repeat(2, 1fr); }
            .show { grid-template-columns: 1fr; gap: 2.5rem; }
            .show:nth-child(even) .show-media { order: 0; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
            .kpis { grid-template-columns: repeat(2, 1fr); }
        }
        @media (min-width: 861px) { .header.is-open .mobile-nav { display: none; } }
        @media (max-width: 860px) {
            .nav, .header-actions .link-signin, .header-actions .btn { display: none; }
            .menu-btn { display: inline-flex; }
            .app { grid-template-columns: 1fr; }
            .app-side { display: none; }
            .facts-grid { grid-template-columns: repeat(2, 1fr); }
            .fact:nth-child(3) { border-left: 0; }
            .fact:nth-child(n+3) { border-top: 1px solid var(--slate-200); }
        }
        @media (max-width: 640px) {
            .container { padding: 0 1rem; }
            .brand img { height: 2.25rem; }
            .hero { padding: 2rem 0 3rem; }
            .section { padding: 4.5rem 0; }
            .grid-3, .steps { grid-template-columns: 1fr; }
            .app-row { grid-template-columns: 1fr; }
            .hero-actions .btn { width: 100%; }
            .footer-grid { grid-template-columns: 1fr; gap: 2rem; }
            .cta { padding: 3rem 1.25rem; }
            .form-grid { grid-template-columns: 1fr; }
            .form-card { padding: 1.5rem 1.25rem; }
            .form-foot .btn { width: 100%; }
            .wa-float { width: 3.5rem; height: 3.5rem; padding: 0; justify-content: center; }
            .wa-float span { display: none; }
            .fact { padding: 1.5rem 1rem; }
            .fact b { font-size: 1.5rem; }
        }
        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            * { transition: none !important; }
        }
    </style>
</head>
<body>

<a href="#main" class="sr-only">Skip to content</a>

<header class="header" id="header">
    <div class="container header-inner">
        <a href="{{ url('/') }}" class="brand" aria-label="SchoolHub home">
            <img src="{{ asset('images/schoolhub-logo-light-cropped.svg') }}" alt="SchoolHub" width="185" height="44">
        </a>

        <nav class="nav" aria-label="Main">
            <a href="#features">Features</a>
            <a href="#modules">Modules</a>
            <a href="#pricing">Pricing</a>
            <a href="#faq">FAQ</a>
            <a href="#demo">Contact</a>
        </nav>

        <div class="header-actions">
            @if ($signedIn)
                <a href="{{ $dashboardUrl }}" class="btn btn-primary btn-sm">Go to dashboard</a>
            @else
                <a href="{{ $loginUrl }}" class="link-signin">Sign in</a>
                <a href="#demo" class="btn btn-secondary btn-sm">Book a demo</a>
                <a href="{{ $registerUrl }}" class="btn btn-primary btn-sm">Start free trial</a>
            @endif
            <button type="button" class="menu-btn" id="menu-btn" aria-expanded="false" aria-controls="mobile-nav" aria-label="Open menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
        </div>
    </div>

    <div class="mobile-nav" id="mobile-nav">
        <a href="#features">Features</a>
        <a href="#modules">Modules</a>
        <a href="#pricing">Pricing</a>
        <a href="#faq">FAQ</a>
        <a href="#demo">Book a demo</a>
        @if ($signedIn)
            <a href="{{ $dashboardUrl }}" class="btn btn-primary btn-block">Go to dashboard</a>
        @else
            <a href="{{ $registerUrl }}" class="btn btn-primary btn-block">Start free trial</a>
            <a href="{{ $loginUrl }}" class="btn btn-secondary btn-block">Sign in</a>
        @endif
    </div>
</header>

<main id="main">

    {{-- ── Hero ── --}}
    <section class="hero">
        <div class="container">
            <div class="hero-copy">
                <span class="badge"><b>New</b> Built for the lower-secondary competency curriculum</span>
                <h1>The complete management system for <span>Ugandan schools</span></h1>
                <p class="hero-lead">Admissions, fees, exams and report cards, payroll and finance — in one secure system that your head teacher, bursar and teachers can all use from any device.</p>

                <div class="hero-actions">
                    @if ($signedIn)
                        <a href="{{ $dashboardUrl }}" class="btn btn-primary btn-lg">Open your dashboard {!! $arrow !!}</a>
                    @else
                        <a href="{{ $registerUrl }}" class="btn btn-primary btn-lg">Register your school {!! $arrow !!}</a>
                        <a href="#demo" class="btn btn-secondary btn-lg">Book a free demo</a>
                    @endif
                </div>

                <div class="hero-points">
                    <span>{!! $check !!} {{ $trialDays }}-day free trial</span>
                    <span>{!! $check !!} No payment details needed</span>
                    <span>{!! $check !!} Set up in minutes</span>
                </div>
            </div>

            <div class="preview" aria-hidden="true">
                <div class="window">
                    <div class="window-bar">
                        <div class="dots"><i></i><i></i><i></i></div>
                        <div class="url">schoolhub · Dashboard</div>
                    </div>
                    <div class="app">
                        <aside class="app-side">
                            <img src="{{ asset('images/schoolhub-logo-light-cropped.svg') }}" alt="">
                            <a class="on"><i></i>Dashboard</a>
                            <p>Students</p>
                            <a><i></i>Students</a>
                            <a><i></i>Promotion</a>
                            <p>Fees</p>
                            <a><i></i>Receive payment</a>
                            <a><i></i>Fee balances</a>
                            <p>Exams &amp; Results</p>
                            <a><i></i>Enter marks</a>
                            <a><i></i>Report cards</a>
                            <p>Human Resources</p>
                            <a><i></i>Payroll</a>
                        </aside>
                        <div class="app-main">
                            <div class="app-head">
                                <div><strong>Dashboard</strong><br><span>Term II · 2026</span></div>
                                <span class="chip">+ Receive payment</span>
                            </div>
                            <div class="kpis">
                                <div class="kpi"><small>Active students</small><b>642</b><em>+18 this term</em></div>
                                <div class="kpi"><small>Fees collected</small><b>UGX 84.2M</b><em>71% of billed</em></div>
                                <div class="kpi"><small>Outstanding</small><b>UGX 34.6M</b><em class="warn">212 students</em></div>
                                <div class="kpi"><small>Staff on payroll</small><b>38</b><em>Payroll ready</em></div>
                            </div>
                            <div class="app-row">
                                <div class="panel">
                                    <div class="panel-title">Fee collection by week</div>
                                    <div class="chart">
                                        <i style="height:28%"></i><i style="height:46%"></i><i style="height:40%"></i><i style="height:62%"></i><i style="height:55%"></i><i style="height:74%"></i><i style="height:68%"></i><i style="height:88%"></i><i style="height:60%"></i><i class="muted" style="height:45%"></i><i class="muted" style="height:38%"></i><i class="muted" style="height:30%"></i>
                                    </div>
                                </div>
                                <div class="panel">
                                    <div class="panel-title">Recent payments</div>
                                    <div class="list">
                                        <div><span><b>Nakato S.</b> · S.2</span><span class="tag tag-green">450,000</span></div>
                                        <div><span><b>Okello B.</b> · P.7</span><span class="tag tag-green">320,000</span></div>
                                        <div><span><b>Achieng M.</b> · S.4</span><span class="tag tag-amber">Part paid</span></div>
                                        <div><span><b>Mugisha D.</b> · P.5</span><span class="tag tag-green">275,000</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Facts ── --}}
    <section class="facts" aria-label="SchoolHub at a glance">
        <div class="container facts-grid">
            <div class="fact"><b>9</b><span>Modules in one system</span></div>
            <div class="fact"><b>{{ $trialDays }} days</b><span>Free trial, all features</span></div>
            <div class="fact"><b>Nursery–S.6</b><span>Primary &amp; secondary</span></div>
            <div class="fact"><b>UGX</b><span>Priced per term</span></div>
        </div>
    </section>

    {{-- ── Features ── --}}
    <section class="section" id="features">
        <div class="container">
            <div class="section-head">
                <div class="eyebrow">Everything in one place</div>
                <h2>Replace the ledgers, spreadsheets and paper files</h2>
                <p>Every plan includes every module. Give each member of staff access to only the parts they need.</p>
            </div>

            <div class="grid-3">
                <div class="card">
                    <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg></div>
                    <h3>Students &amp; guardians</h3>
                    <p>Admissions, classes and streams, parent contacts, bulk import from Excel, and year-end promotion in a few clicks.</p>
                </div>
                <div class="card">
                    <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z"/></svg></div>
                    <h3>Fees &amp; receipts</h3>
                    <p>Fee structures per class, bursaries and discounts, printed receipts, live balances and reminder letters to parents.</p>
                </div>
                <div class="card">
                    <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5"/></svg></div>
                    <h3>Exams &amp; report cards</h3>
                    <p>Teachers enter marks for their own subjects; grades, aggregates, divisions and positions are worked out for you.</p>
                </div>
                <div class="card">
                    <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg></div>
                    <h3>Staff &amp; payroll</h3>
                    <p>Staff records, allowances and deductions, with PAYE, NSSF and LST calculated for you, plus payslips and schedules.</p>
                </div>
                <div class="card">
                    <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg></div>
                    <h3>Finance &amp; budget</h3>
                    <p>Record expenses and other income, set a term budget, and see income against expenditure whenever you need it.</p>
                </div>
                <div class="card">
                    <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg></div>
                    <h3>Access control &amp; audit trail</h3>
                    <p>Each user sees only their modules, and every change is logged — so you always know who did what, and when.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Module showcase ── --}}
    <section class="section section-alt" id="modules">
        <div class="container">
            <div class="section-head">
                <div class="eyebrow">Made for how Ugandan schools work</div>
                <h2>Less time on paperwork, more time on learners</h2>
            </div>

            <div class="showcase">
                <div class="show">
                    <div class="show-copy">
                        <div class="eyebrow">Fees</div>
                        <h3>Know every balance, every day</h3>
                        <p>Bill a whole class in one go, receive payments at the bursar's desk and print a receipt instantly. Balances update the moment money comes in.</p>
                        <ul class="ticks">
                            <li>{!! $check !!} Fee structures per class, term and residency (day or boarding)</li>
                            <li>{!! $check !!} Discounts and bursaries applied automatically</li>
                            <li>{!! $check !!} Reminder letters for parents with outstanding balances</li>
                        </ul>
                    </div>
                    <div class="show-media" aria-hidden="true">
                        <div class="doc">
                            <div class="doc-head">
                                <div><strong>Official receipt</strong><span>No. 001042 · 14 Jul 2026</span></div>
                                <span class="tag tag-green">Paid</span>
                            </div>
                            <div class="doc-rows">
                                <div><span>Student</span><b>Nakato Sarah · S.2 East</b></div>
                                <div><span>Tuition — Term II</span><b>UGX 850,000</b></div>
                                <div><span>Paid by</span><b>Mobile money</b></div>
                                <div><span>Balance remaining</span><b>UGX 400,000</b></div>
                            </div>
                            <div class="doc-total"><span>Amount received</span><span>UGX 450,000</span></div>
                        </div>
                    </div>
                </div>

                <div class="show">
                    <div class="show-copy">
                        <div class="eyebrow">Exams &amp; results</div>
                        <h3>Report cards without the weekend of calculations</h3>
                        <p>Each teacher enters marks for their own subjects. SchoolHub grades them against your scale and prints report cards for the whole class.</p>
                        <ul class="ticks">
                            <li>{!! $check !!} PLE aggregates and divisions for primary</li>
                            <li>{!! $check !!} The competency-based lower-secondary curriculum</li>
                            <li>{!! $check !!} A-Level subject combinations and points</li>
                        </ul>
                    </div>
                    <div class="show-media" aria-hidden="true">
                        <div class="doc" style="padding:0; overflow:hidden">
                            <table class="table">
                                <thead><tr><th>Student</th><th class="num">ENG</th><th class="num">MTC</th><th class="num">SCI</th><th class="num">SST</th><th class="num">Agg</th></tr></thead>
                                <tbody>
                                    <tr><td><b>Okello Brian</b></td><td class="num">D1</td><td class="num">D2</td><td class="num">C3</td><td class="num">D1</td><td class="num"><b>7</b></td></tr>
                                    <tr><td><b>Namuli Grace</b></td><td class="num">D2</td><td class="num">D1</td><td class="num">D1</td><td class="num">D2</td><td class="num"><b>6</b></td></tr>
                                    <tr><td><b>Mugisha David</b></td><td class="num">C3</td><td class="num">C4</td><td class="num">D2</td><td class="num">C3</td><td class="num"><b>12</b></td></tr>
                                    <tr><td><b>Atim Joan</b></td><td class="num">D1</td><td class="num">C3</td><td class="num">D2</td><td class="num">D1</td><td class="num"><b>7</b></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="show">
                    <div class="show-copy">
                        <div class="eyebrow">Payroll</div>
                        <h3>Pay staff correctly, on time</h3>
                        <p>Run the month's payroll in minutes. Statutory deductions are calculated for you, and every staff member gets a clear payslip.</p>
                        <ul class="ticks">
                            <li>{!! $check !!} PAYE, NSSF (5% + 10%) and Local Service Tax</li>
                            <li>{!! $check !!} Allowances, deductions, advances and arrears</li>
                            <li>{!! $check !!} NSSF schedules ready to submit</li>
                        </ul>
                    </div>
                    <div class="show-media" aria-hidden="true">
                        <div class="doc">
                            <div class="doc-head">
                                <div><strong>Payslip — July 2026</strong><span>Ssemwanga Peter · Teacher</span></div>
                            </div>
                            <div class="doc-rows">
                                <div><span>Basic salary</span><b>UGX 1,200,000</b></div>
                                <div><span>Housing allowance</span><b>UGX 150,000</b></div>
                                <div><span>PAYE</span><b>− UGX 307,000</b></div>
                                <div><span>NSSF (5%)</span><b>− UGX 67,500</b></div>
                            </div>
                            <div class="doc-total"><span>Net pay</span><span>UGX 975,500</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── How it works ── --}}
    <section class="section" id="how">
        <div class="container">
            <div class="section-head">
                <div class="eyebrow">Getting started</div>
                <h2>Up and running in an afternoon</h2>
                <p>No installation, no servers and no long training. If your staff can use a smartphone, they can use SchoolHub.</p>
            </div>

            <div class="steps">
                <div class="step">
                    <span class="step-num">1</span>
                    <h3>Register your school</h3>
                    <p>Enter your school's name, category and district, plus your own details. You become the school's administrator immediately.</p>
                </div>
                <div class="step">
                    <span class="step-num">2</span>
                    <h3>Set up and import</h3>
                    <p>Class levels are created for you. Add your streams, term and fee structure, then import learners from Excel.</p>
                </div>
                <div class="step">
                    <span class="step-num">3</span>
                    <h3>Invite your team</h3>
                    <p>Create logins for the bursar, teachers and director of studies, and choose which modules each person can open.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Pricing ── --}}
    @if ($plans->isNotEmpty())
    <section class="section section-alt" id="pricing">
        <div class="container" id="pricing-root" data-cycle="term">
            <div class="section-head">
                <div class="eyebrow">Pricing</div>
                <h2>Simple pricing, paid per term</h2>
                <p>Every plan includes every module. Plans differ by the number of learners, staff logins, and parent &amp; student portal access.</p>
            </div>

            @if ($hasYearly)
                <div class="toggle-wrap">
                    <div class="toggle" role="group" aria-label="Billing period">
                        <button type="button" data-set-cycle="term" aria-pressed="true">Per term</button>
                        <button type="button" data-set-cycle="year" aria-pressed="false">Per year</button>
                    </div>
                </div>
            @endif

            <div class="plans">
                @foreach ($plans as $plan)
                    @php
                        $yearSaving = $plan->price_per_term > 0 && $plan->price_per_year > 0
                            ? (int) round((1 - $plan->price_per_year / ($plan->price_per_term * 3)) * 100)
                            : 0;
                    @endphp
                    <div @class(['plan', 'featured' => $plan->id === $popular])>
                        @if ($plan->id === $popular)<span class="plan-flag">Most popular</span>@endif
                        <h3>{{ $plan->name }}</h3>
                        <p class="plan-desc">{{ $plan->description }}</p>

                        @if ($plan->contact_sales)
                            <div class="plan-price"><b>Custom pricing</b></div>
                            <p class="plan-sub">Sized to your school — talk to us for a quote.</p>
                            <a href="#demo" class="btn btn-block btn-secondary">Contact sales</a>
                        @else
                            <div class="plan-price price-term"><small>UGX</small><b>{{ number_format($plan->price_per_term) }}</b><span>per term</span></div>
                            <div class="plan-price price-year"><small>UGX</small><b>{{ number_format($plan->price_per_year ?: $plan->price_per_term * 3) }}</b><span>per year</span></div>
                            <p class="plan-sub price-term">Billed at the start of each term</p>
                            <p class="plan-sub price-year">{{ $yearSaving > 0 ? "Save {$yearSaving}% compared with paying per term" : 'Billed once a year' }}</p>

                            <a href="{{ $startUrl }}" @class(['btn', 'btn-block', 'btn-primary' => $plan->id === $popular, 'btn-secondary' => $plan->id !== $popular])>Start free trial</a>
                        @endif

                        <ul>
                            <li>{!! $check !!} {{ \App\Models\Plan::limitLabel($plan->max_students) }} active learners</li>
                            <li>{!! $check !!} {{ \App\Models\Plan::limitLabel($plan->max_users) }} staff logins</li>
                            <li>{!! $check !!} All modules included</li>
                            @if ($plan->parent_student_login)
                                <li>{!! $check !!} Free parent &amp; student logins</li>
                            @endif
                        </ul>
                    </div>
                @endforeach
            </div>
            <p class="pricing-note">All schools start with a free {{ $trialDays }}-day trial. Pay by mobile money or bank transfer when you're ready.</p>
        </div>
    </section>
    @endif

    {{-- ── FAQ ── --}}
    <section class="section" id="faq">
        <div class="container">
            <div class="section-head">
                <div class="eyebrow">FAQ</div>
                <h2>Frequently asked questions</h2>
            </div>

            @php
                $faqs = [
                    ['What do I need to register?', 'Only your school\'s name, whether it is a primary or secondary school, its district, and your own name, phone number and email. You can add your logo, address, motto and other details afterwards from the School Profile page.'],
                    ['Who becomes the administrator?', 'The person who registers the school becomes its School Admin, with full access. From there you can create logins for other staff and decide which modules each person can open.'],
                    ["What happens when the {$trialDays}-day trial ends?", "Choose a plan and pay by mobile money or bank transfer. If a payment is late, the school keeps working for {$graceDays} more days. After that, access pauses until payment is recorded — your data is never deleted."],
                    ['Can we import our existing student lists?', 'Yes. Download the template, fill it in from your existing Excel register, and import all your learners in one go.'],
                    ['Does it work for both primary and secondary?', 'Yes. Primary schools get nursery and primary classes with PLE-style grading; secondary schools get O-Level (including the competency-based curriculum) and A-Level with subject combinations.'],
                    ['Do parents and students count towards our staff logins?', 'No — where included, parent and student logins never count towards your staff-login limit. Parent & student portal access comes with the Premium and Enterprise plans; it is not included on Starter or Standard.'],
                ];
            @endphp
            <div class="faq">
                @foreach ($faqs as [$q, $a])
                    <details>
                        <summary>{{ $q }} <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg></summary>
                        <p>{{ $a }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── Book a demo / contact ── --}}
    <section class="section section-alt" id="demo">
        <div class="container demo">
            <div class="demo-intro">
                <div class="eyebrow">Book a demo</div>
                <h2>See SchoolHub working for your school</h2>
                <p>We'll walk you through fees, exams and payroll using a school like yours and answer your questions. The demo is free, takes about 30 minutes, and can be done online or at your school.</p>

                <div class="contact-list">
                    <a href="{{ $waUrl }}" class="contact-item wa" target="_blank" rel="noopener">
                        <span class="icon"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg></span>
                        <span><small>WhatsApp — fastest reply</small><b>{{ $contact['phone'] }}</b></span>
                        <svg class="go" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                    </a>
                    <a href="{{ $telUrl }}" class="contact-item">
                        <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg></span>
                        <span><small>Call us</small><b>{{ $contact['phone'] }}</b></span>
                        <svg class="go" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                    </a>
                    <a href="{{ $mailUrl }}" class="contact-item">
                        <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg></span>
                        <span><small>Email</small><b>{{ $contact['email'] }}</b></span>
                        <svg class="go" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                    </a>
                </div>

                <ul class="expect">
                    <li>{!! $check !!} A live walkthrough using your own classes and fee structure</li>
                    <li>{!! $check !!} Help importing your learners and setting up your first term</li>
                    <li>{!! $check !!} Honest advice on which plan fits your school</li>
                </ul>
            </div>

            <div class="form-card">
                @if ($demoSent)
                    <div class="success" role="status">
                        <span class="icon"><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg></span>
                        <h3>Thank you{{ is_string($demoSent) ? ', ' . $demoSent : '' }}!</h3>
                        <p>We've received your request and will contact you within one working day to arrange your demo. For a faster reply, message us on WhatsApp.</p>
                        <a href="{{ $waUrl }}" class="btn btn-primary" target="_blank" rel="noopener">Message us on WhatsApp</a>
                    </div>
                @else
                    <h3>Request a demo</h3>
                    <p>Tell us a little about your school and we'll get back to you within one working day.</p>

                    @if ($demoErrors->any())
                        <div class="alert alert-error" role="alert">Please check the highlighted fields and try again.</div>
                    @endif

                    <form method="POST" action="{{ route('filament.app.demo-request') }}" novalidate>
                        @csrf
                        <div class="hp" aria-hidden="true">
                            <label for="website">Leave this empty</label>
                            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="form-grid">
                            <div @class(['field', 'has-error' => $demoErrors->has('name')])>
                                <label for="d-name">Your name</label>
                                <input id="d-name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required maxlength="150" placeholder="e.g. Sarah Nakato">
                                @if ($demoErrors->has('name'))<span class="err">{{ $demoErrors->first('name') }}</span>@endif
                            </div>
                            <div @class(['field', 'has-error' => $demoErrors->has('school_name')])>
                                <label for="d-school">School name</label>
                                <input id="d-school" name="school_name" type="text" value="{{ old('school_name') }}" autocomplete="organization" required maxlength="150" placeholder="Your school's name">
                                @if ($demoErrors->has('school_name'))<span class="err">{{ $demoErrors->first('school_name') }}</span>@endif
                            </div>
                            <div @class(['field', 'has-error' => $demoErrors->has('phone')])>
                                <label for="d-phone">Phone / WhatsApp</label>
                                <input id="d-phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" required placeholder="07XX XXX XXX">
                                @if ($demoErrors->has('phone'))<span class="err">{{ $demoErrors->first('phone') }}</span>@endif
                            </div>
                            <div @class(['field', 'has-error' => $demoErrors->has('email')])>
                                <label for="d-email">Email <i>(optional)</i></label>
                                <input id="d-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="you@school.ac.ug">
                                @if ($demoErrors->has('email'))<span class="err">{{ $demoErrors->first('email') }}</span>@endif
                            </div>
                            <div @class(['field', 'has-error' => $demoErrors->has('learners')])>
                                <label for="d-learners">Number of learners <i>(optional)</i></label>
                                <select id="d-learners" name="learners">
                                    <option value="">Select…</option>
                                    @foreach ($learnerRanges as $value => $label)
                                        <option value="{{ $value }}" @selected(old('learners') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @if ($demoErrors->has('learners'))<span class="err">{{ $demoErrors->first('learners') }}</span>@endif
                            </div>
                            <div @class(['field', 'has-error' => $demoErrors->has('preferred_date')])>
                                <label for="d-date">Preferred date <i>(optional)</i></label>
                                <input id="d-date" name="preferred_date" type="date" value="{{ old('preferred_date') }}" min="{{ today()->toDateString() }}">
                                @if ($demoErrors->has('preferred_date'))<span class="err">{{ $demoErrors->first('preferred_date') }}</span>@endif
                            </div>
                            <div @class(['field', 'full', 'has-error' => $demoErrors->has('preferred_contact')])>
                                <span class="field-label" id="d-contact-label">How should we contact you?</span>
                                <div class="radios" role="radiogroup" aria-labelledby="d-contact-label">
                                    @foreach ($contactMethods as $value => $label)
                                        <label><input type="radio" name="preferred_contact" value="{{ $value }}" @checked(old('preferred_contact', 'whatsapp') === $value)><span>{{ $label }}</span></label>
                                    @endforeach
                                </div>
                                @if ($demoErrors->has('preferred_contact'))<span class="err">{{ $demoErrors->first('preferred_contact') }}</span>@endif
                            </div>
                            <div @class(['field', 'full', 'has-error' => $demoErrors->has('message')])>
                                <label for="d-message">Anything we should know? <i>(optional)</i></label>
                                <textarea id="d-message" name="message" maxlength="2000" placeholder="e.g. We are a day and boarding secondary school and want to move our fees off Excel.">{{ old('message') }}</textarea>
                                @if ($demoErrors->has('message'))<span class="err">{{ $demoErrors->first('message') }}</span>@endif
                            </div>
                        </div>

                        <div class="form-foot">
                            <small>We only use your details to arrange your demo.</small>
                            <button type="submit" class="btn btn-primary btn-lg">Request my demo {!! $arrow !!}</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </section>

    {{-- ── CTA ── --}}
    <section class="container">
        <div class="cta">
            <h2>Ready to run your school the modern way?</h2>
            <p>Register in about a minute and try every module free for {{ $trialDays }} days. No payment details needed.</p>
            <div class="hero-actions">
                @if ($signedIn)
                    <a href="{{ $dashboardUrl }}" class="btn btn-white btn-lg">Open your dashboard {!! $arrow !!}</a>
                @else
                    <a href="{{ $registerUrl }}" class="btn btn-white btn-lg">Register your school {!! $arrow !!}</a>
                    <a href="#demo" class="btn btn-outline-white btn-lg">Book a demo</a>
                @endif
            </div>
        </div>
    </section>
</main>

<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <img src="{{ asset('images/schoolhub-logo-sidebar.svg') }}" alt="SchoolHub" width="185" height="44">
                <p class="footer-about">School management software for Ugandan primary and secondary schools.</p>
            </div>
            <div>
                <h4>Product</h4>
                <ul>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#modules">Modules</a></li>
                    <li><a href="#pricing">Pricing</a></li>
                </ul>
            </div>
            <div>
                <h4>Get started</h4>
                <ul>
                    <li><a href="{{ $registerUrl }}">Register your school</a></li>
                    <li><a href="{{ $loginUrl }}">Sign in</a></li>
                    <li><a href="{{ filament()->getRequestPasswordResetUrl() }}">Reset password</a></li>
                    <li><a href="{{ route('filament.app.legal.terms') }}">Terms &amp; conditions</a></li>
                </ul>
            </div>
            <div>
                <h4>Support</h4>
                <ul>
                    <li><a href="#demo">Book a demo</a></li>
                    <li><a href="{{ $waUrl }}" target="_blank" rel="noopener">WhatsApp {{ $contact['phone'] }}</a></li>
                    <li><a href="{{ $telUrl }}">Call {{ $contact['phone'] }}</a></li>
                    <li><a href="{{ $mailUrl }}">{{ $contact['email'] }}</a></li>
                    <li><a href="#faq">FAQ</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© {{ date('Y') }} SchoolHub. All rights reserved.</span>
            <span>Developed by {{ $contact['company'] }} · Made in Uganda</span>
        </div>
    </div>
</footer>

<a href="{{ $waUrl }}" class="wa-float" target="_blank" rel="noopener" aria-label="Chat with us on WhatsApp"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg><span>Chat with us</span></a>

<script>
    (function () {
        var header = document.getElementById('header');
        var menuBtn = document.getElementById('menu-btn');

        // Border and shadow on the sticky header once the page scrolls.
        var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();

        // Mobile menu.
        menuBtn.addEventListener('click', function () {
            var open = header.classList.toggle('is-open');
            menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.querySelectorAll('#mobile-nav a').forEach(function (a) {
            a.addEventListener('click', function () {
                header.classList.remove('is-open');
                menuBtn.setAttribute('aria-expanded', 'false');
            });
        });

        // Per term / per year pricing.
        var root = document.getElementById('pricing-root');
        document.querySelectorAll('[data-set-cycle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                root.setAttribute('data-cycle', btn.dataset.setCycle);
                document.querySelectorAll('[data-set-cycle]').forEach(function (b) {
                    b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
                });
            });
        });
    })();
</script>
</body>
</html>
