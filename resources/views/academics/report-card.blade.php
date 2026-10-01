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
    $exam = $results['exam'] ?? null;
@endphp

@section('title', "Report cards — {$class->name} — {$term->label()}")

@section('styles')
    @page { size: A4; margin: 10mm; }
    .sheet { --rc-primary: {{ $style['primary'] }}; --rc-accent: {{ $style['accent'] }}; --rc-on-primary: {{ $style['onPrimary'] }}; }
    .sheet { max-width: 210mm; padding: 1.4rem 1.6rem; font-size: 12px; }
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

    /* Modern: the school's name on a coloured band. */
    .design-modern .rc-top { background: var(--rc-primary); color: var(--rc-on-primary); border-bottom: 4px solid var(--rc-accent); padding: .7rem .9rem; border-radius: 6px 6px 0 0; }
    .design-modern .rc-top h1, .design-modern .rc-top p { color: var(--rc-on-primary); }
    .design-modern .rc-top img.logo { background: #fff; border-radius: 50%; padding: .2rem; }
    .design-modern .rc-top .photo { border: 2px solid var(--rc-accent); }
    .design-modern table.marks th { background: var(--rc-primary); color: var(--rc-on-primary); border-color: var(--rc-primary); }
    .design-modern .box { border-left: 4px solid var(--rc-accent); }

    /* Compact: smaller type and spacing so long subject lists fit one page. */
    .sheet.design-compact { font-size: 10.5px; padding: 1rem 1.2rem; }
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
        @endphp
        <div class="sheet design-{{ $style['design'] }} font-{{ $style['font'] }} border-{{ $style['border'] }}">
            @if ($template->watermark && $logo)
                <div class="watermark"><img src="{{ $logo }}" alt=""></div>
            @endif
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
                        @foreach ($columns as $a)
                            <th class="c" title="{{ $a->name }}">{{ $a->shortLabel() }}<br><span style="font-weight:500">/{{ $a->max_score + 0 }}</span></th>
                        @endforeach
                        <th class="c">{{ $exam ? '%' : 'Term %' }}</th>
                        <th class="c">Grade</th>
                        <th style="text-align:left">{{ $curriculum === 'o_level' ? 'Achievement' : 'Remark' }}</th>
                        @if ($show('teacher_initials'))<th class="c">Teacher</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($row['subjects'] as $subjectId => $res)
                        <tr @class(['not-counted' => $counted !== null && ! in_array($subjectId, $counted, true)])>
                            <td><strong>{{ $res['subject']->name }}</strong></td>
                            @foreach ($columns as $a)
                                @php $s = $res['scores'][$a->id] ?? null; @endphp
                                <td class="c">{{ $s ? ($s['absent'] ? 'AB' : $n($s['raw'])) : '' }}</td>
                            @endforeach
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
                </div>
            @endif

            @if ($template->footer_text)
                <div class="rc-footer">{{ $template->footer_text }}</div>
            @endif
        </div>
    @endforeach
@endsection
