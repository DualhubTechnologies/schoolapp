    {{-- ── Insights: what SchoolHub points out each day (App\Services\SchoolInsights).
         Every item listed is a real check the system runs; the card shows sample figures. ── --}}
    @php
        $checks = [
            'Money received today, and whether fee collection is ahead of or behind last term',
            'Learners in the system who have not been billed, or a new term not billed at all',
            'Learners who have paid nothing this term, and families who always pay but have not yet',
            'The same amount recorded twice for a learner on the same day',
            'Learners with no parent or phone number on record',
            'Learners whose marks fell by ten points or more since last term',
        ];
        $samples = [
            ['is-danger', 'Possible double payment to check', 'UGX 300,000 recorded twice for Okello Brian (S.3) on 14 Oct.'],
            ['is-warning', 'Fee collection is behind last term', '41% collected by week 5, against 58% at the same point last term.'],
            ['is-info', '6 families that always pay have not paid yet', 'A reminder usually does it: Nakato S. (S.2), Mugisha D. (P.5) and 4 more.'],
        ];
    @endphp
    <section class="section" id="insights">
        <div class="container">
            <div class="ai" data-reveal>
                <div class="ai-copy">
                    <div class="eyebrow">Insights · available now</div>
                    <h2>SchoolHub tells you what needs your attention today</h2>
                    <p>Every morning SchoolHub reads the school's own records and points out what a director or bursar would want to know, with the learners named and a button to deal with it. No reports to dig through.</p>
                    <ul class="ticks">
                        @foreach ($checks as $item)
                            <li>{!! $check !!} {{ $item }}</li>
                        @endforeach
                    </ul>
                    <p class="ai-note">It pops up once a day on the dashboard, and is always under Insights in the menu. Each person sees only the areas they are allowed to open.</p>
                </div>

                <div class="ai-media" aria-hidden="true">
                    <div class="ai-pop">
                        <div class="ai-pop-head">
                            <span class="ai-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/></svg></span>
                            <div><strong>Insights</strong><small>What your school's records point out today</small></div>
                        </div>
                        @foreach ($samples as [$tone, $title, $detail])
                            <div class="ai-item {{ $tone }}">
                                <span class="ai-dot"></span>
                                <div><b>{{ $title }}</b><span>{{ $detail }}</span></div>
                                <span class="ai-open">Open</span>
                            </div>
                        @endforeach
                        <p class="ai-sample">Sample figures</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <style>
        .ai { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 3.5rem; align-items: center; }
        .ai-copy h2 { margin-top: .75rem; font-size: clamp(1.75rem, 3.2vw, 2.375rem); font-weight: 800; letter-spacing: -.03em; }
        .ai-copy > p { margin-top: 1rem; font-size: 1.0625rem; color: var(--slate-600); }
        .ai-note { margin-top: 1.25rem; font-size: .9rem !important; color: var(--slate-500) !important; }
        .ai-media { padding: 1.5rem; border-radius: var(--radius-lg); background: linear-gradient(160deg, #16335c, #0d1f38); box-shadow: var(--shadow); }
        .ai-pop { background: #fff; border-radius: 14px; border-top: 3px solid #d4a72c; padding: 1.1rem 1.1rem .75rem; box-shadow: 0 24px 50px -24px rgba(0, 0, 0, .6); }
        .ai-pop-head { display: flex; align-items: center; gap: .75rem; margin-bottom: .5rem; }
        .ai-badge { flex: none; display: grid; place-items: center; width: 2.4rem; height: 2.4rem; border-radius: .7rem; background: #12294a; color: #f3d77a; }
        .ai-badge svg { width: 1.3rem; height: 1.3rem; }
        .ai-pop-head strong { display: block; font-family: Georgia, serif; font-size: 1.1rem; color: #12294a; }
        .ai-pop-head small { font-size: .78rem; color: #64748b; }
        .ai-item { display: flex; align-items: flex-start; gap: .75rem; padding: .75rem 0; border-top: 1px solid #f1f5f9; }
        .ai-dot { flex: none; width: .65rem; height: .65rem; margin-top: .35rem; border-radius: 50%; }
        .ai-item.is-danger .ai-dot { background: #dc2626; }
        .ai-item.is-warning .ai-dot { background: #d97706; }
        .ai-item.is-info .ai-dot { background: #2563eb; }
        .ai-item > div { flex: 1; min-width: 0; }
        .ai-item b { display: block; font-size: .9rem; color: #16233a; }
        .ai-item div span { display: block; margin-top: .1rem; font-size: .8rem; line-height: 1.45; color: #64748b; }
        .ai-open { flex: none; align-self: center; font-size: .75rem; font-weight: 600; color: #1b3a63; padding: .25rem .6rem; border: 1px solid #d6dee9; border-radius: 7px; }
        .ai-sample { margin-top: .25rem; text-align: right; font-size: .7rem; color: #94a3b8; }
        @media (max-width: 960px) { .ai { grid-template-columns: 1fr; gap: 2rem; } }
        @media (max-width: 640px) {
            .ai-media { padding: .875rem; }
            .ai-open { display: none; }
            .ai-note { display: none; }
        }
    </style>
