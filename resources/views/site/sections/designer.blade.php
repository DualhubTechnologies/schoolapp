    {{-- ── Report card designer: try the school's own look ──
         The choices are the real ones on the Report card design page
         (App\Models\ReportCardTemplate: designs, fonts, borders, two colours,
         watermark, wording and the parts shown), so nothing here promises
         more than a school can set. --}}
    @php
        $designs = ['classic' => 'Classic', 'modern' => 'Modern', 'compact' => 'Compact'];
        $borders = ['none' => 'None', 'line' => 'Single', 'double' => 'Double', 'ornate' => 'Ornate'];
        $fonts = ['sans' => 'Plain', 'serif' => 'Traditional'];
        $palettes = [
            ['#1e3a5f', '#c8a24a', 'Navy and gold'],
            ['#14532d', '#c8a24a', 'Green and gold'],
            ['#7f1d1d', '#d4a72c', 'Maroon and gold'],
            ['#1d4ed8', '#f59e0b', 'Royal blue and orange'],
            ['#5b21b6', '#eab308', 'Purple and yellow'],
            ['#0f172a', '#94a3b8', 'Black and silver'],
        ];
        $parts = ['Student photo', 'LIN / combination', 'Marks for each exam', 'Topic levels', 'Formative and exam parts', 'Project work', 'Average for each term', 'Teacher initials', 'Total marks', 'Position in class', 'Position in stream', 'Conduct', 'Promotion', 'Next term begins', 'Fees balance', 'Class teacher\'s comment', 'Head teacher\'s comment', 'Signatures', 'Grading key', 'QR code'];
        $subjects = [['English Language', 'B', 'Outstanding'], ['Mathematics', 'A', 'Exceptional'], ['Biology', 'B', 'Outstanding'], ['Geography', 'C', 'Satisfactory'], ['Chemistry', 'A', 'Exceptional']];
    @endphp
    <section class="section" id="report-card-designer">
        <div class="container">
            <div class="section-head" data-reveal>
                <div class="eyebrow">Report cards, your way</div>
                <h2>Not a fixed format. Your school's own report card.</h2>
                <p>Most systems give you a handful of fixed report cards. In SchoolHub the school designs its own: the layout, colours, lettering, border, wording and exactly which parts are printed. Try it below.</p>
            </div>

            <div class="rcd" data-reveal>
                <div class="rcd-try">
                    <div class="rcd-controls" role="group" aria-label="Try a design">
                        <div class="rcd-ctl">
                            <span>Layout</span>
                            <div class="rcd-seg">
                                @foreach ($designs as $value => $label)
                                    <button type="button" data-set="design" data-value="{{ $value }}" @class(['is-on' => $loop->first])>{{ $label }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div class="rcd-ctl">
                            <span>Colours</span>
                            <div class="rcd-swatches">
                                @foreach ($palettes as [$primary, $accent, $label])
                                    <button type="button" data-set="colours" data-value="{{ $primary }} {{ $accent }}" title="{{ $label }}" aria-label="{{ $label }}" @class(['is-on' => $loop->first]) style="--p: {{ $primary }}; --a: {{ $accent }}"></button>
                                @endforeach
                            </div>
                        </div>
                        <div class="rcd-ctl">
                            <span>Border</span>
                            <div class="rcd-seg">
                                @foreach ($borders as $value => $label)
                                    <button type="button" data-set="border" data-value="{{ $value }}" @class(['is-on' => $value === 'double'])>{{ $label }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div class="rcd-ctl">
                            <span>Lettering</span>
                            <div class="rcd-seg">
                                @foreach ($fonts as $value => $label)
                                    <button type="button" data-set="font" data-value="{{ $value }}" @class(['is-on' => $loop->first])>{{ $label }}</button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- A small sample card that follows the choices above. --}}
                    <div class="rcd-stage">
                        <div class="rcd-card" data-design="classic" data-border="double" data-font="sans" style="--p: #1e3a5f; --a: #c8a24a" aria-label="A sample report card in the chosen design">
                            <div class="rcd-head">
                                <span class="rcd-crest" aria-hidden="true"></span>
                                <div>
                                    <strong>YOUR SCHOOL NAME</strong>
                                    <small>P.O. Box 100, Your Town · "Your school motto"</small>
                                </div>
                                <span class="rcd-photo" aria-hidden="true"></span>
                            </div>
                            <div class="rcd-title"><span>Learner's Achievement Report</span><small>Term 3 · 2026</small></div>
                            <div class="rcd-who"><span><b>Name</b> Nakato Sarah</span><span><b>Class</b> S.2 East</span></div>
                            <table class="rcd-table">
                                <thead><tr><th>Subject</th><th>Score</th><th>Grade</th><th>Achievement</th></tr></thead>
                                <tbody>
                                    @foreach ($subjects as $i => [$subject, $grade, $achievement])
                                        <tr><td>{{ $subject }}</td><td>{{ [2.6, 2.9, 2.5, 2.1, 2.8][$i] }}</td><td>{{ $grade }}</td><td>{{ $achievement }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="rcd-foot">
                                <div><b>Head teacher's comment</b><span>A hardworking learner. Keep it up.</span></div>
                                <span class="rcd-qr" aria-hidden="true"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rcd-what">
                    <h3>What each school decides</h3>
                    <ul class="ticks">
                        <li>{!! $check !!} <span><b>Three layouts</b>: Classic, Modern with a coloured band, or Compact for classes with many subjects</span></li>
                        <li>{!! $check !!} <span><b>Any two colours</b>, to match your crest and uniform</span></li>
                        <li>{!! $check !!} <span><b>Four borders</b> and plain or traditional lettering</span></li>
                        <li>{!! $check !!} <span><b>Your own wording</b>: the report's title, a note under the heading and the text at the foot</span></li>
                        <li>{!! $check !!} <span><b>Your crest</b> on every card, and as a faint watermark if you like</span></li>
                        <li>{!! $check !!} <span><b>Primary, O-Level and A-Level</b>, each graded the right way</span></li>
                    </ul>
                    <p class="rcd-parts-h"><b>Choose which of {{ count($parts) }} parts to print:</b></p>
                    <div class="rcd-parts">
                        @foreach ($parts as $part)
                            <span>{{ $part }}</span>
                        @endforeach
                    </div>
                    <p class="rcd-note">Set it once on the Report card design page, and every class's cards print in your school's look.</p>
                </div>
            </div>
        </div>
    </section>

    <style>
        .rcd { display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr); gap: 3rem; align-items: start; }
        .rcd-try { padding: 1.25rem; border: 1px solid var(--slate-200); border-radius: var(--radius-lg); background: linear-gradient(180deg, var(--slate-50), #fff); box-shadow: var(--shadow); }
        .rcd-controls { display: grid; grid-template-columns: 1fr 1fr; gap: .9rem 1.25rem; }
        .rcd-ctl > span { display: block; margin-bottom: .35rem; font-size: .75rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--slate-500); }
        .rcd-seg { display: inline-flex; flex-wrap: wrap; gap: .25rem; padding: .2rem; border: 1px solid var(--slate-200); border-radius: .6rem; background: #fff; }
        .rcd-seg button { border: 0; background: none; padding: .35rem .65rem; border-radius: .45rem; font: inherit; font-size: .8125rem; font-weight: 600; color: var(--slate-600); cursor: pointer; }
        .rcd-seg button.is-on { background: var(--blue-600); color: #fff; }
        .rcd-swatches { display: flex; flex-wrap: wrap; gap: .45rem; }
        .rcd-swatches button { width: 1.9rem; height: 1.9rem; border-radius: 50%; border: 2px solid #fff; cursor: pointer; background: linear-gradient(135deg, var(--p) 0 58%, var(--a) 58% 100%); box-shadow: 0 0 0 1px var(--slate-300); }
        .rcd-swatches button.is-on { box-shadow: 0 0 0 2px var(--blue-600); }

        .rcd-stage { margin-top: 1.25rem; display: flex; justify-content: center; }
        .rcd-card { --pad: 1rem; width: 100%; max-width: 30rem; background: #fff; color: #1f2937; font-size: .78rem; line-height: 1.4; padding: var(--pad); box-shadow: 0 18px 40px -20px rgba(15, 23, 42, .45); transition: all .35s ease; }
        .rcd-card[data-font="serif"] { font-family: Georgia, "Times New Roman", serif; }
        .rcd-card[data-border="line"] { outline: 2px solid var(--p); outline-offset: -8px; }
        .rcd-card[data-border="double"] { outline: 4px double var(--p); outline-offset: -9px; }
        .rcd-card[data-border="ornate"] { outline: 4px double var(--p); outline-offset: -9px; box-shadow: inset 0 0 0 13px #fff, inset 0 0 0 15px var(--a), 0 18px 40px -20px rgba(15, 23, 42, .45); }
        .rcd-card[data-border]:not([data-border="none"]) { --pad: 1.4rem; }
        .rcd-head { display: flex; align-items: center; gap: .7rem; padding-bottom: .55rem; border-bottom: 3px double var(--p); transition: all .35s ease; }
        .rcd-head > div { flex: 1; text-align: center; }
        .rcd-head strong { display: block; font-size: .95rem; letter-spacing: .04em; color: var(--p); }
        .rcd-head small { color: #6b7280; font-size: .68rem; }
        .rcd-crest { flex: none; width: 2.3rem; height: 2.3rem; border-radius: 50%; background: radial-gradient(circle at 50% 45%, var(--a) 0 28%, var(--p) 29% 62%, var(--a) 63% 70%, #fff 71%); box-shadow: 0 0 0 1px var(--p); }
        .rcd-photo { flex: none; width: 2rem; height: 2.4rem; border-radius: 3px; background: #e5e7eb; border: 2px solid var(--a); }
        .rcd-card[data-design="modern"] .rcd-head { margin: calc(-1 * var(--pad) + .6rem) calc(-1 * var(--pad) + .6rem) 0; padding: .7rem; background: var(--p); border-bottom: 3px solid var(--a); }
        .rcd-card[data-design="modern"] .rcd-head strong, .rcd-card[data-design="modern"] .rcd-head small { color: #fff; }
        .rcd-title { margin: .6rem 0 .5rem; text-align: center; }
        .rcd-title span { display: inline-block; padding: .2rem .75rem; background: var(--p); color: #fff; font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; border-bottom: 2px solid var(--a); }
        .rcd-title small { display: block; margin-top: .2rem; color: #6b7280; }
        .rcd-who { display: flex; justify-content: space-between; gap: 1rem; padding: .4rem .6rem; border: 1px solid #e5e7eb; border-radius: 4px; margin-bottom: .5rem; }
        .rcd-who b { display: block; font-size: .6rem; letter-spacing: .06em; text-transform: uppercase; color: #6b7280; }
        .rcd-table { width: 100%; border-collapse: collapse; }
        .rcd-table th { text-align: left; padding: .35rem .4rem; font-size: .62rem; letter-spacing: .05em; text-transform: uppercase; color: var(--p); background: color-mix(in srgb, var(--p) 9%, #fff); border-bottom: 2px solid var(--p); transition: all .35s ease; }
        .rcd-card[data-design="modern"] .rcd-table th { background: var(--p); color: #fff; border-bottom-color: var(--a); }
        .rcd-table td { padding: .32rem .4rem; border-bottom: 1px solid #eef0f3; }
        .rcd-table td:nth-child(3) { font-weight: 700; color: var(--p); }
        .rcd-foot { display: flex; align-items: center; gap: .75rem; margin-top: .6rem; padding: .5rem .6rem; border-left: 3px solid var(--a); background: #fafafa; }
        .rcd-foot div { flex: 1; }
        .rcd-foot b { display: block; font-size: .62rem; letter-spacing: .05em; text-transform: uppercase; color: var(--p); }
        .rcd-foot span { font-style: italic; }
        .rcd-qr { flex: none; width: 2.1rem; height: 2.1rem; background: repeating-conic-gradient(#111 0 25%, #fff 0 50%) 0 0 / .42rem .42rem; border: 2px solid #111; }
        .rcd-card[data-design="compact"] { font-size: .7rem; line-height: 1.25; }
        .rcd-card[data-design="compact"] .rcd-table td, .rcd-card[data-design="compact"] .rcd-table th { padding: .2rem .35rem; }
        .rcd-card[data-design="compact"] .rcd-head strong { font-size: .85rem; }

        .rcd-what h3 { font-size: 1.35rem; font-weight: 800; letter-spacing: -.02em; }
        .rcd-what .ticks { margin-top: 1rem; }
        .rcd-what .ticks span b { color: var(--slate-900); }
        .rcd-parts-h { margin-top: 1.5rem; font-size: .9375rem; color: var(--slate-700); }
        .rcd-parts { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .6rem; }
        .rcd-parts span { padding: .25rem .6rem; border: 1px solid var(--slate-200); border-radius: 999px; background: #fff; font-size: .78rem; color: var(--slate-600); }
        .rcd-note { margin-top: 1.25rem; font-size: .9rem; color: var(--slate-500); }

        @media (max-width: 960px) {
            .rcd { grid-template-columns: 1fr; gap: 2rem; }
        }
        @media (max-width: 520px) {
            .rcd-controls { grid-template-columns: 1fr; }
            .rcd-try { padding: 1rem; }
            .rcd-card { font-size: .7rem; }
            .rcd-table th:nth-child(4), .rcd-table td:nth-child(4) { display: none; }
        }
    </style>

    <script>
        // The sample card follows each choice: layout, colours, border, lettering.
        document.querySelectorAll('#report-card-designer [data-set]').forEach(function (button) {
            button.addEventListener('click', function () {
                var card = document.querySelector('#report-card-designer .rcd-card');
                var setting = button.dataset.set;

                if (setting === 'colours') {
                    var colours = button.dataset.value.split(' ');
                    card.style.setProperty('--p', colours[0]);
                    card.style.setProperty('--a', colours[1]);
                } else {
                    card.dataset[setting] = button.dataset.value;
                }

                document.querySelectorAll('#report-card-designer [data-set="' + setting + '"]').forEach(function (other) {
                    other.classList.toggle('is-on', other === button);
                });
            });
        });
    </script>
