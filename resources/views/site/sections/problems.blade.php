    {{-- ── The problems each person in a school lives with, and what changes ── --}}
    @php
        $problems = [
            ['Directors and owners', 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z',
                ['No clear picture of how much has been collected, or where it went', 'Money that is hard to trace when a receipt book goes missing'],
                'See fees collected, spending and every balance from your phone, with every change recorded against a name.'],
            ['Bursars', 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z',
                ['Balances worked out by hand in exercise books and Excel', 'Parents queueing to ask what they owe'],
                'Bill a whole class at once, print a receipt in seconds, and every balance is always up to date.'],
            ['Teachers and directors of studies', 'M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5',
                ['Late nights adding marks and writing out report cards', 'Grading mistakes that parents notice before you do'],
                'Teachers enter marks, even from a phone. Grades, positions and report cards for the whole class are done in seconds.'],
            ['Parents', 'M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3',
                ['Not knowing the balance until the start of term', 'No proof that a payment reached the school'],
                'An SMS reminder before arrears build up, a receipt anyone can check with its QR code, and results on their phone.'],
        ];
    @endphp
    <section class="section section-alt" id="problems">
        <div class="container">
            <div class="section-head" data-reveal>
                <div class="eyebrow">Sound familiar?</div>
                <h2>Every school knows these problems</h2>
                <p>Paper, Excel and receipt books cost schools hours every week, and money that cannot be traced. SchoolHub puts everyone on one system.</p>
            </div>

            <div class="pb-grid">
                @foreach ($problems as [$who, $icon, $pains, $fix])
                    <article class="pb-card" data-reveal>
                        <div class="pb-who">
                            <span class="pb-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg></span>
                            <h3>{{ $who }}</h3>
                        </div>
                        <ul class="pb-pains">
                            @foreach ($pains as $pain)
                                <li>{{ $pain }}</li>
                            @endforeach
                        </ul>
                        <p class="pb-fix"><b>With SchoolHub:</b> {{ $fix }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <style>
        .pb-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1.25rem; }
        .pb-card { display: flex; flex-direction: column; padding: 1.5rem; border: 1px solid var(--slate-200); border-radius: var(--radius-lg); background: #fff; box-shadow: var(--shadow-sm); }
        .pb-who { display: flex; align-items: center; gap: .75rem; }
        .pb-icon { flex: none; display: grid; place-items: center; width: 2.5rem; height: 2.5rem; border-radius: .75rem; background: var(--blue-50); color: var(--blue-600); }
        .pb-icon svg { width: 1.35rem; height: 1.35rem; }
        .pb-who h3 { font-size: 1.0625rem; font-weight: 700; }
        .pb-pains { list-style: none; margin-top: 1rem; display: grid; gap: .5rem; }
        .pb-pains li { position: relative; padding-left: 1.4rem; font-size: .9375rem; color: var(--slate-600); }
        .pb-pains li::before { content: ""; position: absolute; left: 0; top: .45rem; width: .75rem; height: .75rem; border-radius: 50%; background: #fee2e2; box-shadow: inset 0 0 0 3px #fca5a5; }
        .pb-fix { margin-top: auto; padding-top: 1rem; border-top: 1px dashed var(--slate-200); font-size: .9375rem; color: var(--slate-700); }
        .pb-pains + .pb-fix { margin-top: 1rem; }
        .pb-fix b { color: #047857; }
        @media (max-width: 1080px) { .pb-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        /* Phones: the four cards side by side, to swipe through. */
        @media (max-width: 640px) {
            .pb-grid { display: flex; gap: .875rem; overflow-x: auto; scroll-snap-type: x mandatory; margin: 0 -1rem; padding: .25rem 1rem 1rem; -webkit-overflow-scrolling: touch; }
            .pb-card { flex: 0 0 84%; scroll-snap-align: center; padding: 1.125rem; }
        }
    </style>
