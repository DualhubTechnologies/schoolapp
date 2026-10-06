@extends('fees.layout')

@php
    use Illuminate\Support\Facades\Storage;

    $curriculum = $results['curriculum'];
    $assessments = $results['assessments'];
    $logo = $school?->logo ? Storage::disk('public')->url($school->logo) : null;
    $signature = $school?->signatureUrl();
    $n = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    $title = $template->titleFor($curriculum);
    $style = $template->style();
    $show = fn (string $part): bool => $template->shows($part);
    $columns = $show('assessment_columns') ? $assessments : collect();
    // Project marks are printed in their own section instead.
    if ($show('projects') && $projects) {
        $columns = $columns->reject(fn ($a) => $a->type === 'project');
    }
    $split = $show('split_columns') ? ($results['split'] ?? null) : null;
    $exam = $results['exam'] ?? null;
    // O-Level with the formative/exam split: each CA out of 3 (CA1, CA2, ...),
    // whatever it was marked out of, their average, the CA out of 20 and End
    // of Term out of 80.
    $outOf3 = fn ($pct) => $pct === null ? '—' : rtrim(rtrim(number_format($pct * \App\Models\Assessment::O_LEVEL_CA_REPORT_SCALE / 100, 2), '0'), '.');
    $caColumns = $split && ! $exam ? $columns->filter(fn ($a) => $a->isAveragedIn($curriculum))->values() : collect();
    $eotColumns = $caColumns->isNotEmpty() ? $columns->where('type', 'eot')->values() : collect();
    if ($caColumns->isNotEmpty()) {
        $columns = $columns->reject(fn ($a) => $a->isAveragedIn($curriculum) || $a->type === 'eot');
    }
@endphp

@section('title', "Report cards — {$class->name} — {$term->label()}")

@section('styles')
    /* One report card = one A4 page. The page margin is the sheet's padding,
       so the comments, signatures and key always sit at the foot of the page,
       and a long report is scaled down to fit (see the script below). */
    @page { size: A4; margin: 0; }
    .sheet { --rc-primary: {{ $style['primary'] }}; --rc-accent: {{ $style['accent'] }}; --rc-on-primary: {{ $style['onPrimary'] }}; }
    .sheet { width: 210mm; max-width: 100%; height: 297mm; padding: 11mm 12mm; font-size: 12px; display: flex; flex-direction: column; }
    .rc-fit { flex: 1; display: flex; flex-direction: column; min-height: 0; }
    .rc-bottom { margin-top: auto; padding-top: .6rem; }
    /* Spare room on a short report, shared out by the script below. */
    .rc-fit table.marks:not(.projects) tbody td { padding-top: calc(.32rem + var(--rc-row-extra, 0px)); padding-bottom: calc(.32rem + var(--rc-row-extra, 0px)); }
    .rc-fit .comment .line { min-height: calc(2.2rem + var(--rc-line-extra, 0px)); }
    @media print { .sheet { width: 210mm; max-width: none; height: 297mm; } }
    .sheet.font-serif { font-family: Georgia, "Times New Roman", Times, serif; }
    .sheet.border-line { outline: 2px solid var(--rc-primary); outline-offset: -8px; }
    .sheet.border-double { outline: 5px double var(--rc-primary); outline-offset: -9px; }
    .sheet.border-ornate { outline: 5px double var(--rc-primary); outline-offset: -9px; box-shadow: inset 0 0 0 13px #fff, inset 0 0 0 14px var(--rc-accent); }
    .sheet > * { position: relative; }
    .sheet > .watermark { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; }
    .sheet > .watermark img { width: 55%; max-width: 110mm; opacity: .06; }
    .rc-top { display: flex; align-items: center; gap: 1rem; padding-bottom: .7rem; border-bottom: 3px double var(--rc-primary); }
    .rc-top img.logo { width: 4.2rem; height: 4.2rem; object-fit: contain; }
    .rc-top .who { flex: 1; text-align: center; }
    .rc-top h1 { font-size: 1.25rem; font-weight: 800; letter-spacing: .03em; text-transform: uppercase; color: var(--rc-primary); }
    .rc-top p { font-size: .75rem; color: #4b5563; }
    .rc-top .photo { width: 4.2rem; height: 4.8rem; object-fit: cover; border: 1px solid #cbd5e1; border-radius: 4px; }
    .rc-title { text-align: center; margin: .7rem 0 .6rem; }
    .rc-title h2 { display: inline-block; font-size: .95rem; letter-spacing: .12em; text-transform: uppercase; background: var(--rc-primary); color: var(--rc-on-primary); padding: .25rem 1rem; border-radius: 3px; border-bottom: 3px solid var(--rc-accent); }
    .rc-title p { font-size: .8rem; color: #374151; margin-top: .25rem; font-weight: 600; }
    .info { display: grid; grid-template-columns: repeat(4, 1fr); gap: .35rem 1rem; border: 1px solid #cbd5e1; border-radius: 6px; padding: .55rem .8rem; margin-bottom: .7rem; }
    .info .label { font-size: .62rem; }
    .info .v { font-weight: 700; color: #16233a; }
    table.marks { width: 100%; border-collapse: collapse; }
    table.marks th { background: #eef3fa; font-size: .65rem; text-transform: uppercase; letter-spacing: .03em; color: var(--rc-primary); padding: .35rem .4rem; border: 1px solid #cbd5e1; }
    table.marks td { padding: .32rem .4rem; border: 1px solid #dfe5ee; }
    table.marks td.c, table.marks th.c { text-align: center; }
    table.marks td.grade { font-weight: 800; color: var(--rc-primary); text-align: center; }
    table.marks tr.not-counted td { color: #6b7280; }
    .summary { display: grid; grid-template-columns: 1.2fr 1fr; gap: .8rem; margin-top: .7rem; }
    .box { border: 1px solid #cbd5e1; border-radius: 6px; padding: .55rem .8rem; }
    .big { font-size: 1.15rem; font-weight: 800; color: var(--rc-primary); }
    .kv { display: flex; justify-content: space-between; gap: .5rem; padding: .12rem 0; }
    .kv b { color: #16233a; }
    .comment { margin-top: .55rem; }
    .comment .line { min-height: 2.2rem; border-bottom: 1px dotted #94a3b8; padding: .1rem 0 .2rem; font-style: italic; }
    .foot { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.2rem; margin-top: 1.1rem; align-items: end; }
    .sig { border-top: 1px solid #111827; padding-top: .2rem; text-align: center; font-size: .7rem; color: #374151; }
    .sig img { max-height: 2.4rem; display: block; margin: 0 auto .15rem; }
    .key { margin-top: .6rem; font-size: .66rem; color: #4b5563; }
    .key span { display: inline-block; margin-right: .6rem; }
    .fees { margin-top: .6rem; background: #f8fafc; }
    .rc-footer { margin-top: .7rem; padding-top: .35rem; border-top: 1px solid var(--rc-accent); text-align: center; font-size: .68rem; color: #4b5563; white-space: pre-line; }
    .header-note { font-weight: 600; }
    .section-title { margin: .7rem 0 .25rem; font-size: .7rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--rc-primary); }
    .trend { margin-top: .6rem; display: flex; flex-wrap: wrap; gap: .3rem 1.2rem; align-items: baseline; }
    .trend .label { font-size: .62rem; }
    .trend-term b { color: var(--rc-primary); }
    .paper { display: block; font-size: .62rem; white-space: nowrap; }
    td.topics { white-space: normal; }
    .tp { display: inline-flex; align-items: center; gap: 2px; margin: 1px 4px 1px 0; font-size: .62rem; color: #4b5563; }
    .lv { display: inline-grid; place-items: center; width: 14px; height: 14px; border-radius: 3px; color: #fff; font-style: normal; font-weight: 700; font-size: .6rem; }
    .lv0 { background: #c2410c; } .lv1 { background: #d97706; } .lv2 { background: #65a30d; } .lv3 { background: #15803d; }
    .topics-box { margin-top: .6rem; }
    .uce-note { font-size: .68rem; color: #4b5563; margin-bottom: .15rem; }

    /* Modern: the school's name on a coloured band. */
    .design-modern .rc-top { background: var(--rc-primary); color: var(--rc-on-primary); border-bottom: 4px solid var(--rc-accent); padding: .7rem .9rem; border-radius: 6px 6px 0 0; }
    .design-modern .rc-top h1, .design-modern .rc-top p { color: var(--rc-on-primary); }
    .design-modern .rc-top img.logo { background: #fff; border-radius: 50%; padding: .2rem; }
    .design-modern .rc-top .photo { border: 2px solid var(--rc-accent); }
    .design-modern table.marks th { background: var(--rc-primary); color: var(--rc-on-primary); border-color: var(--rc-primary); }
    .design-modern .box { border-left: 4px solid var(--rc-accent); }

    /* Compact: smaller type and spacing so long subject lists fit one page. */
    .sheet.design-compact { font-size: 10.5px; padding: 9mm 10mm; }
    .design-compact .rc-top { padding-bottom: .45rem; }
    .design-compact .rc-top img.logo, .design-compact .rc-top .photo { width: 3.3rem; height: 3.6rem; }
    .design-compact .rc-top h1 { font-size: 1.05rem; }
    .design-compact .rc-title { margin: .45rem 0 .4rem; }
    .design-compact table.marks td, .design-compact table.marks th { padding: .2rem .35rem; }
    .design-compact .summary { margin-top: .45rem; gap: .5rem; }
    .design-compact .box { padding: .4rem .6rem; }
    .design-compact .comment .line { min-height: 1.6rem; }
    .design-compact .foot { margin-top: .8rem; }
@endsection

@section('content')
    @foreach ($rows as $row)
        @php
            $student = $row['student'];
            $report = $row['report'];
            $photo = $student->photoUrl();
            $counted = $row['counted_subject_ids'] ?? null;
            $topics = $topicScores[$student->id] ?? [];
            $allTopics = collect($topics)->flatten(1);
            $achieved = $allTopics->filter(fn ($t) => $t->level >= \App\Models\TopicScore::ACHIEVED_FROM);
            $needsSupport = collect($topics)
                ->map(fn ($list, $subjectId) => collect($list)->filter(fn ($t) => $t->level < \App\Models\TopicScore::ACHIEVED_FROM))
                ->filter(fn ($list) => $list->isNotEmpty())
                ->map(fn ($list, $subjectId) => ($row['subjects'][$subjectId]['subject']->name ?? 'Subject').' '.$list->map(fn ($t) => $t->topic->shortLabel())->implode(', '));
        @endphp
        <div class="sheet design-{{ $style['design'] }} font-{{ $style['font'] }} border-{{ $style['border'] }}">
            @if ($template->watermark && $logo)
                <div class="watermark"><img src="{{ $logo }}" alt=""></div>
            @endif
            <div class="rc-fit">
            <div class="rc-top">
                @if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif
                <div class="who">
                    <h1>{{ $school?->name }}</h1>
                    @if ($school?->address)<p>{{ $school->address }}</p>@endif
                    <p>{{ collect([$school?->phone, $school?->email])->filter()->implode('  ·  ') }}</p>
                    @if ($template->header_note)<p class="header-note">{{ $template->header_note }}</p>@endif
                    @if ($school?->motto)<p><em>“{{ $school->motto }}”</em></p>@endif
                </div>
                @if ($show('photo'))<img class="photo" src="{{ $photo }}" alt="">@endif
            </div>

            <div class="rc-title">
                <h2>{{ $title }}</h2>
                <p>{{ $term->label() }}{{ $exam ? ' — '.$exam->name : '' }}</p>
            </div>

            <div class="info">
                <div><div class="label">Name</div><div class="v">{{ $student->name }}</div></div>
                <div><div class="label">Admission no.</div><div class="v">{{ $student->admission_no }}</div></div>
                <div><div class="label">Class</div><div class="v">{{ $class->name }}{{ $student->section ? ' · ' . $student->section->name : '' }}</div></div>
                @if ($show('identifiers'))
                    <div>
                        @if ($curriculum === 'a_level')
                            <div class="label">Combination</div><div class="v">{{ $student->combination?->label() ?? '—' }}</div>
                        @else
                            <div class="label">LIN</div><div class="v">{{ $student->lin ?: '—' }}</div>
                        @endif
                    </div>
                @endif
            </div>

            <table class="marks">
                <thead>
                    <tr>
                        <th style="text-align:left">Subject</th>
                        @if ($topicScores)<th style="text-align:left">Topics (0–3)</th>@endif
                        @foreach ($columns as $a)
                            <th class="c" title="{{ $a->name }}">{{ $a->shortLabel() }}<br><span style="font-weight:500">/{{ $a->max_score + 0 }}</span></th>
                        @endforeach
                        @foreach ($caColumns as $a)
                            <th class="c" title="{{ $a->name }}">CA{{ $loop->iteration }}<br><span style="font-weight:500">/3</span></th>
                        @endforeach
                        @if ($caColumns->isNotEmpty())
                            <th class="c">CA avg<br><span style="font-weight:500">/3</span></th>
                        @endif
                        @if ($split)
                            <th class="c">{{ $caColumns->isNotEmpty() ? 'CA' : 'Formative' }}<br><span style="font-weight:500">/{{ $split['formative'] }}</span></th>
                            <th class="c">{{ $caColumns->isNotEmpty() ? 'End of Term' : 'Exam' }}<br><span style="font-weight:500">/{{ $split['summative'] }}</span></th>
                        @endif
                        <th class="c">{{ $exam ? '%' : ($split ? 'Total /100' : 'Term %') }}</th>
                        <th class="c">Grade</th>
                        <th style="text-align:left">{{ $curriculum === 'o_level' ? 'Achievement' : 'Remark' }}</th>
                        @if ($show('teacher_initials'))<th class="c">Teacher</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($row['subjects'] as $subjectId => $res)
                        <tr @class(['not-counted' => $counted !== null && ! in_array($subjectId, $counted, true)])>
                            <td><strong>{{ $res['subject']->name }}</strong></td>
                            @if ($topicScores)
                                <td class="topics">@foreach ($topics[$subjectId] ?? [] as $t)<span class="tp" title="{{ $t->topic->label() }}"><i class="lv lv{{ $t->level }}">{{ $t->level }}</i>{{ $t->topic->shortLabel() }}</span>@endforeach</td>
                            @endif
                            @foreach ($columns as $a)
                                @php $s = $res['scores'][$a->id] ?? null; @endphp
                                <td class="c">
                                    @if ($s && ! empty($s['papers']))
                                        @foreach ($s['papers'] as $paper => $paperScore)<span class="paper">P{{ $paper }} {{ $paperScore === null ? 'AB' : $n($paperScore) }}</span>@endforeach
                                    @else
                                        {{ $s ? ($s['absent'] ? 'AB' : $n($s['raw'])) : '' }}
                                    @endif
                                </td>
                            @endforeach
                            @if ($caColumns->isNotEmpty())
                                @php
                                    $caPcts = [];
                                @endphp
                                @foreach ($caColumns as $a)
                                    @php
                                        $s = $res['scores'][$a->id] ?? null;
                                        if ($s && $s['pct'] !== null) {
                                            $caPcts[] = $s['pct'];
                                        }
                                    @endphp
                                    <td class="c">{{ $s ? ($s['absent'] ? 'AB' : $outOf3($s['pct'])) : '' }}</td>
                                @endforeach
                                <td class="c">{{ $caPcts ? $outOf3(array_sum($caPcts) / count($caPcts)) : '—' }}</td>
                            @endif
                            @if ($split)
                                <td class="c">{{ $n($res['formative']) }}</td>
                                <td class="c">
                                    {{ $n($res['summative']) }}
                                    {{-- A subject sat as several papers: each paper's End of Term mark. --}}
                                    @foreach ($eotColumns as $a)
                                        @foreach ($res['scores'][$a->id]['papers'] ?? [] as $paper => $paperScore)<span class="paper">P{{ $paper }} {{ $paperScore === null ? 'AB' : $n($paperScore) }}</span>@endforeach
                                    @endforeach
                                </td>
                            @endif
                            <td class="c"><strong>{{ $n($res['final']) }}</strong></td>
                            <td class="grade">{{ $res['grade'] ?? '—' }}</td>
                            <td>{{ $res['comment'] ?: $res['descriptor'] }}</td>
                            @if ($show('teacher_initials'))<td class="c">{{ $teachers[$res['subject']->pivot->teacher_id ?? 0] ?? '' }}</td>@endif
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="summary">
                <div class="box">
                    @if ($curriculum === 'primary')
                        <div class="kv"><span>Aggregate (4 core subjects)</span><b class="big">{{ $row['aggregate'] ?? 'X' }}</b></div>
                        <div class="kv"><span>Division</span><b class="big">{{ $row['division'] === 'X' ? 'Incomplete' : ($row['division'] ?? '—') }}</b></div>
                    @elseif ($curriculum === 'a_level')
                        <div class="kv"><span>Total points (out of 20)</span><b class="big">{{ $row['points'] ?? '—' }}</b></div>
                        <div class="kv"><span>Principal grades</span><b>{{ $row['principal_grades'] ?: '—' }}</b></div>
                        <div class="kv"><span>Result</span><b>{{ $row['result_code'] ?: '—' }}</b></div>
                    @elseif ($curriculum === 'o_level')
                        <div class="kv"><span>Result</span><b class="big">{{ $row['uce_label'] ?? '—' }}</b></div>
                        @if ($row['uce_result'] ?? null)
                            <div class="uce-note">{{ \App\Services\Academics\ResultsCalculator::UCE_MEANINGS[$row['uce_result']] }}@if ($row['uce_reasons']): {{ implode('; ', $row['uce_reasons']) }}@endif.</div>
                        @endif
                    @else
                        <div class="kv"><span>Overall achievement</span><b class="big">{{ $row['overall_grade'] ?? '—' }}</b></div>
                        <div class="kv"><span>Descriptor</span><b>{{ $row['overall_descriptor'] ?? '—' }}</b></div>
                    @endif
                    <div class="kv"><span>Average score</span><b>{{ $n($row['average']) }}%</b></div>
                    @if ($show('total_marks'))<div class="kv"><span>Total marks</span><b>{{ $n($row['total']) }}</b></div>@endif
                </div>
                <div class="box">
                    @if ($show('class_position'))
                        <div class="kv"><span>Position in class</span><b class="big">{{ $row['position'] ?? '—' }} <span style="font-size:.75rem;font-weight:600">out of {{ $row['out_of'] }}</span></b></div>
                    @endif
                    @if ($show('stream_position') && $student->section && $row['stream_position'])
                        <div class="kv"><span>Position in stream</span><b>{{ $row['stream_position'] }} out of {{ $row['stream_out_of'] }}</b></div>
                    @endif
                    @if ($show('conduct') && $report?->conduct)<div class="kv"><span>Conduct</span><b>{{ $report->conduct }}</b></div>@endif
                    @php $days = $attendance[$student->id] ?? null; @endphp
                    @if ($report?->days_present)
                        <div class="kv"><span>Days present</span><b>{{ $report->days_present }}</b></div>
                    @elseif ($days && $days['days'])
                        <div class="kv"><span>Attendance</span><b>{{ $days['present'] }} of {{ $days['days'] }} days</b></div>
                    @endif
                    @if (! empty($promotionText[$student->id]))
                        <div class="kv"><span>Promotion</span><b style="color:var(--rc-primary)">{{ $promotionText[$student->id] }}</b></div>
                    @endif
                    @if ($show('next_term'))
                        <div class="kv"><span>Next term begins</span><b>{{ $nextTerm?->start_date?->format('j M Y') ?? 'To be communicated' }}</b></div>
                    @endif
                </div>
            </div>

            @if (! empty($projects[$student->id]))
                <div class="section-title">Project work</div>
                <table class="marks projects">
                    <thead>
                        <tr>
                            <th style="text-align:left">Project</th>
                            <th style="text-align:left">Subject</th>
                            <th class="c">Score</th>
                            <th class="c">%</th>
                            <th class="c">Grade</th>
                            <th style="text-align:left">Remark</th>
                            @if ($show('teacher_initials'))<th class="c">Teacher</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($projects[$student->id] as $project)
                            <tr>
                                <td><strong>{{ $project['title'] }}</strong></td>
                                <td>{{ $project['subject'] }}</td>
                                <td class="c">{{ $n($project['score']) }} / {{ $n($project['max']) }}</td>
                                <td class="c">{{ $n($project['percent']) }}%</td>
                                <td class="grade">{{ $project['grade'] ?? '—' }}</td>
                                <td>{{ $project['remark'] }}</td>
                                @if ($show('teacher_initials'))<td class="c">{{ $project['teacher'] }}</td>@endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if (! empty($termAverages[$student->id]))
                <div class="box trend">
                    <span class="label">Average by term</span>
                    @foreach ($termAverages[$student->id] as $termName => $average)
                        <span class="trend-term">{{ $termName }} <b>{{ $average === null ? '—' : $n($average).'%' }}</b></span>
                    @endforeach
                </div>
            @endif

            @if ($allTopics->isNotEmpty())
                <div class="box topics-box">
                    <div class="kv"><span>Topics achieved (level 2 or 3)</span><b>{{ $achieved->count() }} of {{ $allTopics->count() }}</b></div>
                    @if ($needsSupport->isNotEmpty())
                        <div class="kv"><span>Needs support in</span><b>{{ $needsSupport->implode('; ') }}</b></div>
                    @endif
                </div>
            @endif

            @if ($showFees)
                @php
                    $balance = $student->balance();
                    $next = $nextFees->filter(fn ($f) => $f->appliesToResidency($student->residency_type_id))->sum('amount');
                @endphp
                <div class="box fees">
                    <div class="kv">
                        <span>Fees balance{{ $balance < 0 ? ' (in credit)' : '' }}</span>
                        <b>UGX {{ number_format(abs($balance)) }}</b>
                    </div>
                    @if ($next > 0)
                        <div class="kv"><span>Next term's fees</span><b>UGX {{ number_format($next) }}</b></div>
                        <div class="kv"><span>Total payable by the start of next term</span><b>UGX {{ number_format(max($balance, 0) + $next) }}</b></div>
                    @endif
                </div>
            @endif

            <div class="rc-bottom">
            @if ($show('class_teacher_comment'))
                <div class="comment">
                    <div class="label">Class teacher's comment</div>
                    <div class="line">{{ $report?->class_teacher_comment }}</div>
                </div>
            @endif
            @if ($show('head_teacher_comment'))
                <div class="comment">
                    <div class="label">Head teacher's comment</div>
                    <div class="line">{{ $report?->head_teacher_comment }}</div>
                </div>
            @endif

            @if ($show('signatures'))
                <div class="foot">
                    <div class="sig">Class teacher</div>
                    <div class="sig">@if ($signature)<img src="{{ $signature }}" alt="">@endif Head teacher</div>
                    <div class="sig">Parent / Guardian</div>
                </div>
            @endif

            @php $keyScale = $show('grading_key') ? $scales->get($curriculum === 'a_level' ? 'principal' : 'subject') : null; @endphp
            @if ($keyScale)
                <div class="key">
                    <strong>Key:</strong>
                    @foreach ($keyScale->bands as $band)
                        <span>{{ $band->grade }} {{ $band->min_score + 0 }}–{{ $band->max_score + 0 }}{{ $band->descriptor ? ' ' . $band->descriptor : '' }}</span>
                    @endforeach
                    @if ($curriculum === 'a_level')<span>· Subsidiary pass (D1–C6) = 1 point</span>@endif
                    @if ($topicScores)<br><strong>Topics:</strong> <span>3 all outcomes, with ease</span><span>2 most, enough to achieve</span><span>1 some, not enough</span><span>0 none yet</span>@endif
                    @if ($curriculum === 'o_level')<br><strong>Result:</strong> <span>1 qualifies for the UCE certificate</span><span>2 requirements missing</span><span>3 E in every subject</span>@endif
                </div>
            @endif

            @if ($template->footer_text)
                <div class="rc-footer">{{ $template->footer_text }}</div>
            @endif
            </div>
            </div>
        </div>
    @endforeach

    <script>
        // Fit each report card on one A4 page: a short one gets more room
        // between rows, a long one is scaled down until it fits. The bottom
        // part (comments, signatures, key) stays at the foot either way.
        function fitReportCards() {
            document.querySelectorAll('.sheet').forEach((sheet) => {
                const fit = sheet.querySelector('.rc-fit');
                if (! fit) {
                    return;
                }
                const style = getComputedStyle(sheet);
                const room = sheet.clientHeight - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom);
                const needed = () => {
                    fit.style.flex = 'none';
                    fit.style.height = 'auto';
                    return fit.scrollHeight;
                };

                fit.style.zoom = 1;
                fit.style.rowGap = '';
                fit.style.setProperty('--rc-row-extra', '0px');
                fit.style.setProperty('--rc-line-extra', '0px');

                let height = needed();
                if (height > room) {
                    // Too long: the largest scale at which the whole card
                    // fits the page (text wraps less as it shrinks, so search).
                    let low = 0.3;
                    let high = 1;
                    for (let i = 0; i < 12; i++) {
                        const zoom = (low + high) / 2;
                        fit.style.zoom = zoom;
                        if (needed() * zoom <= room) {
                            low = zoom;
                        } else {
                            high = zoom;
                        }
                    }
                    fit.style.zoom = low;
                    fit.style.height = (room / low) + 'px';
                    return;
                }

                // Room to spare: taller subject rows, more space to write the
                // comments, then even gaps between the parts; whatever is
                // left goes above the comments and signatures. Scaled back
                // together if that is more than the page holds.
                const spare = room - height;
                const rows = fit.querySelectorAll('table.marks:not(.projects) tbody tr').length || 1;
                const lines = fit.querySelectorAll('.comment .line').length || 1;
                const gaps = Math.max(fit.children.length - 1, 1);
                const share = (part) => {
                    fit.style.setProperty('--rc-row-extra', Math.min(spare * 0.35 * part / rows / 2, 9) + 'px');
                    fit.style.setProperty('--rc-line-extra', Math.min(spare * 0.25 * part / lines, 40) + 'px');
                    fit.style.rowGap = Math.min(spare * 0.2 * part / gaps, 14) + 'px';
                    return needed() <= room;
                };
                if (! share(1)) {
                    let low = 0;
                    let high = 1;
                    for (let i = 0; i < 10; i++) {
                        const part = (low + high) / 2;
                        if (share(part)) {
                            low = part;
                        } else {
                            high = part;
                        }
                    }
                    share(low);
                }
                fit.style.flex = '';
                fit.style.height = '';
            });
        }
        fitReportCards();
        window.addEventListener('load', fitReportCards);
        window.addEventListener('beforeprint', fitReportCards);
        document.fonts?.ready.then(fitReportCards);
    </script>
@endsection
