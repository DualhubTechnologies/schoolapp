{{-- The public site's styles (home page and the other public pages). Raw CSS, inside a <style> tag. --}}
        :root {
            /* Neutrals (slate) */
            --slate-900: #0f172a; --slate-800: #1e293b; --slate-700: #334155; --slate-600: #475569;
            --slate-500: #64748b; --slate-400: #94a3b8; --slate-300: #cbd5e1; --slate-200: #e2e8f0;
            --slate-100: #f1f5f9; --slate-50: #f8fafc;
            /* Primary (blue) */
            --blue-700: #1d4ed8; --blue-600: #2563eb; --blue-500: #3b82f6; --blue-100: #dbeafe; --blue-50: #eff6ff;
            --indigo-600: #4f46e5; --indigo-500: #6366f1; --sky-400: #38bdf8;
            --gradient: linear-gradient(135deg, var(--blue-600) 0%, var(--indigo-600) 100%);
            --gradient-text: linear-gradient(90deg, var(--blue-600) 0%, var(--indigo-500) 55%, var(--sky-400) 100%);
            --glow: 0 10px 30px -10px rgba(37, 99, 235, .55);
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
        .hero-signin { margin-top: 1rem; font-size: .9375rem; color: var(--slate-600); }
        .hero-signin a { font-weight: 600; color: var(--blue-600); text-decoration: underline; text-underline-offset: 3px; }
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
        .app-head .chip { padding: .3125rem .625rem; border-radius: .375rem; background: var(--blue-600); color: #fff; font-size: .6875rem; font-weight: 600; }
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
        /* Let the columns shrink below their content's width, so a wide
           mock-up can't push the page sideways on small phones. */
        .show > * { min-width: 0; }
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

        /* ── Automation ("you do / SchoolHub does") ── */
        .auto-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; }
        .auto { position: relative; display: flex; flex-direction: column; gap: .875rem; padding: 1.5rem; border: 1px solid var(--slate-200); border-radius: var(--radius-lg); background: #fff; box-shadow: var(--shadow-sm); transition: transform .2s, box-shadow .2s, border-color .2s; }
        .auto:hover { transform: translateY(-3px); border-color: var(--blue-100); box-shadow: var(--shadow-lg); }
        .auto-you { display: inline-flex; align-self: flex-start; align-items: center; gap: .375rem; padding: .25rem .625rem; border-radius: 999px; background: var(--slate-100); font-size: .75rem; font-weight: 600; color: var(--slate-600); }
        .auto-you svg { width: .875rem; height: .875rem; }
        .auto-does { display: flex; gap: .75rem; align-items: flex-start; }
        .auto-does .icon { flex: none; }
        .auto-does div b { display: block; font-size: 1rem; font-weight: 700; color: var(--slate-900); line-height: 1.35; }
        .auto-does div span { display: block; margin-top: .25rem; font-size: .875rem; color: var(--slate-600); }

        /* ── Visual polish ── */
        .btn-primary { background: var(--gradient); border: 0; box-shadow: var(--glow); }
        .btn-primary:hover { background: var(--gradient); filter: brightness(1.08); box-shadow: 0 14px 34px -10px rgba(37, 99, 235, .65); }
        .btn-lg, .btn-primary, .btn-secondary, .btn-white { transition: transform .15s, box-shadow .15s, background-color .15s, border-color .15s, filter .15s; }
        .btn-lg:hover { transform: translateY(-1px); }

        .hero { padding-bottom: 5rem; }
        .hero::after { content: ""; position: absolute; inset: -10rem -20% auto; height: 44rem; z-index: -2; pointer-events: none;
            background:
                radial-gradient(38% 45% at 22% 30%, rgba(59, 130, 246, .20), transparent 70%),
                radial-gradient(34% 40% at 80% 22%, rgba(99, 102, 241, .20), transparent 70%),
                radial-gradient(26% 32% at 62% 62%, rgba(56, 189, 248, .16), transparent 70%),
                radial-gradient(18% 22% at 35% 70%, rgba(245, 158, 11, .10), transparent 70%); }
        .hero h1 span { background: var(--gradient-text); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .badge b { background: var(--gradient); color: #fff; }

        .preview-float { position: absolute; z-index: 2; display: flex; align-items: center; gap: .625rem; padding: .625rem .875rem .625rem .625rem; border: 1px solid rgba(226, 232, 240, .9); border-radius: .875rem; background: rgba(255, 255, 255, .92); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); box-shadow: var(--shadow-lg); font-size: .75rem; color: var(--slate-600); text-align: left; animation: float 6s ease-in-out infinite; }
        .preview-float b { display: block; font-size: .8125rem; font-weight: 700; color: var(--slate-900); }
        .preview-float .dot { flex: none; display: flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: .625rem; color: #fff; background: var(--gradient); }
        .preview-float .dot.green { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .preview-float .dot.amber { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
        .preview-float .dot svg { width: 1.125rem; height: 1.125rem; }
        .pf-1 { top: 18%; left: -3.5rem; }
        .pf-2 { top: 22%; right: -3.5rem; animation-delay: -2s; }
        .pf-3 { bottom: -1.5rem; left: 14%; animation-delay: -4s; }
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }

        /* Wide screens: copy on the left, the product preview beside it, both above the fold. */
        @media (min-width: 1101px) {
            .hero-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.15fr); align-items: center; gap: 3.5rem; }
            .hero-grid .hero-copy { max-width: none; margin: 0; text-align: left; }
            .hero-grid h1 { font-size: clamp(2.5rem, 4vw, 3.375rem); }
            .hero-grid .hero-lead { margin-left: 0; font-size: 1.125rem; }
            .hero-grid .hero-actions, .hero-grid .hero-points { justify-content: flex-start; }
            .hero-grid .hero-points { gap: .5rem 1.25rem; }
            .hero-grid .preview { margin: 0; max-width: none; }
            .hero-grid .app { grid-template-columns: 1fr; min-height: 0; }
            .hero-grid .app-side { display: none; }
            .hero-grid .kpis { grid-template-columns: repeat(2, 1fr); }
            .hero-grid .pf-1 { top: -1.25rem; left: auto; right: 1.5rem; }
            .hero-grid .pf-2 { display: none; }
            .hero-grid .pf-3 { bottom: -1.25rem; left: -1.5rem; }
        }

        .fact b { background: var(--gradient-text); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .section-alt { background: linear-gradient(180deg, var(--slate-50), #fff 85%); }
        .eyebrow { display: inline-flex; align-items: center; gap: .375rem; padding: .25rem .75rem; border-radius: 999px; background: var(--blue-50); border: 1px solid var(--blue-100); font-size: .8125rem; }
        .show-copy .eyebrow { padding: .1875rem .625rem; }
        .icon { background: var(--gradient); color: #fff; border: 0; box-shadow: var(--glow); }
        .card:hover { transform: translateY(-3px); border-color: var(--blue-100); box-shadow: var(--shadow-lg); }
        .card { transition: transform .2s, border-color .2s, box-shadow .2s; }
        .step-num { background: var(--gradient); box-shadow: var(--glow); }
        .step { transition: transform .2s, box-shadow .2s; }
        .step:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
        .plan { transition: transform .2s, box-shadow .2s; }
        .plan:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
        .plan.featured { border: 2px solid transparent; background: linear-gradient(#fff, #fff) padding-box, var(--gradient) border-box; box-shadow: 0 30px 60px -20px rgba(79, 70, 229, .35); }
        .plan-flag { background: var(--gradient); box-shadow: var(--glow); }
        .contact-item.wa .icon { box-shadow: none; }
        .success .icon { box-shadow: none; }
        .cta { background: var(--gradient); box-shadow: 0 30px 60px -24px rgba(79, 70, 229, .55); }
        .cta::after { content: ""; position: absolute; inset: -40% -10% auto auto; width: 28rem; height: 28rem; border-radius: 50%; background: radial-gradient(closest-side, rgba(56, 189, 248, .45), transparent); pointer-events: none; }

        /* Fade-in on scroll. Only hidden once the script confirms it can reveal them. */
        /* An animation (not a transition) so hover effects keep their own timing afterwards. */
        .reveal-ready [data-reveal]:not(.is-visible) { opacity: 0; }
        .reveal-ready [data-reveal].is-visible { animation: reveal .6s ease backwards; }
        @keyframes reveal { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: none; } }

        /* ── Responsive ── */
        @media (max-width: 1100px) {
            .preview-float { display: none; }
            .nav { gap: 1.25rem; }
            .header-actions .btn-secondary { display: none; }
        }
        @media (max-width: 1024px) {
            .auto-grid { grid-template-columns: repeat(2, 1fr); }
            .demo { grid-template-columns: 1fr; }
            .grid-3, .steps { grid-template-columns: repeat(2, 1fr); }
            .show { grid-template-columns: 1fr; gap: 2.5rem; }
            .show:nth-child(even) .show-media { order: 0; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
            .kpis { grid-template-columns: repeat(2, 1fr); }
        }
        @media (min-width: 861px) { .header.is-open .mobile-nav { display: none; } }
        @media (max-width: 860px) {
            /* Phones keep Sign in (or Go to dashboard) beside the menu button. */
            .nav, .header-actions .btn:not(.header-keep) { display: none; }
            .header-actions { gap: .5rem; }
            .header-actions .link-signin { display: inline-flex; align-items: center; height: 2.5rem; padding: 0 1rem; border: 1px solid var(--slate-300); border-radius: .5rem; background: #fff; font-size: .875rem; color: var(--slate-900); }
            .header-actions .header-keep { height: 2.5rem; }
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
            .grid-3, .steps, .auto-grid { grid-template-columns: 1fr; }
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
            * { transition: none !important; animation: none !important; }
            .reveal-ready [data-reveal]:not(.is-visible) { opacity: 1; }
        }

        /* ── Public pages other than the home page ── */
        .nav a[aria-current="page"], .mobile-nav a[aria-current="page"] { color: var(--blue-600); font-weight: 600; }
        .page-hero { padding: 4.5rem 0 3rem; text-align: center; background: linear-gradient(180deg, var(--blue-50) 0%, #fff 100%); border-bottom: 1px solid var(--slate-100); }
        .page-hero h1 { margin-top: .5rem; font-size: clamp(2rem, 4.5vw, 3rem); font-weight: 800; letter-spacing: -.03em; line-height: 1.1; }
        .page-hero p { margin: 1rem auto 0; max-width: 42rem; font-size: 1.125rem; color: var(--slate-600); }
        .page-hero .hero-actions { justify-content: center; margin-top: 2rem; }
        .prose { max-width: 46rem; margin: 0 auto; font-size: 1.0625rem; color: var(--slate-700); }
        .prose h2 { margin-top: 2.5rem; font-size: 1.5rem; font-weight: 800; letter-spacing: -.02em; color: var(--slate-900); }
        .prose h2:first-child { margin-top: 0; }
        .prose p { margin-top: 1rem; }
        .prose ul.ticks { margin-top: 1rem; }
        .prose a { color: var(--blue-600); font-weight: 600; text-decoration: underline; text-underline-offset: 2px; }
        .info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; }
        .team-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(13rem, 16rem)); justify-content: center; gap: 2rem; }
        .team-card { display: flex; flex-direction: column; align-items: center; text-align: center; padding: 1rem 1rem 1.75rem; border-radius: var(--radius-lg); background: var(--slate-100); }
        .team-photo { display: grid; place-items: end center; width: 100%; max-width: 12.25rem; aspect-ratio: 1; overflow: hidden; border-radius: 50%; background: #e0e3fb; color: #8f96c8; }
        .team-photo img { width: 100%; height: 100%; object-fit: cover; }
        .team-photo svg { width: 80%; height: 80%; }
        .team-card h2 { margin-top: 1.25rem; font-size: 1rem; font-weight: 800; color: var(--slate-900); }
        .team-role { margin-top: .25rem; font-size: .9375rem; line-height: 1.4; color: var(--blue-600); }
        .team-links { display: flex; gap: .875rem; justify-content: center; margin-top: auto; padding-top: 1.25rem; }
        .team-links a:not(.btn) { display: inline-flex; color: var(--slate-700); }
        .team-links a:not(.btn):hover { color: var(--blue-600); }
        .team-links svg { width: 1.25rem; height: 1.25rem; }
        .info { padding: 1.5rem; border: 1px solid var(--slate-200); border-radius: var(--radius-lg); background: #fff; }
        .info small { display: block; font-size: .8125rem; font-weight: 600; color: var(--slate-500); text-transform: uppercase; letter-spacing: .04em; }
        .info b { display: block; margin-top: .4rem; font-size: 1.125rem; color: var(--slate-900); }
        .info span { display: block; margin-top: .25rem; font-size: .9375rem; color: var(--slate-600); }
        .info a { color: var(--blue-600); font-weight: 600; }
        @media (max-width: 900px) { .info-grid { grid-template-columns: 1fr; } .page-hero { padding: 3rem 0 2rem; } }
