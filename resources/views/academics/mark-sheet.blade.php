@extends('fees.layout')

@php
    $logo = $school?->logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($school->logo) : null;
    $max = (float) $assessment->max_score;
    $n = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $entered = $marks->filter(fn ($m) => $m->score !== null || $m->is_absent);
@endphp

@section('title', "Mark sheet — {$subject->name} — {$class->name}")

@section('styles')
    @page { size: A4; margin: 12mm; }
    .sheet { max-width: 210mm; padding: 1.5rem 1.75rem; font-size: 12.5px; }
    .ms-head { display: grid; grid-template-columns: repeat(3, 1fr); gap: .4rem 1rem; margin: .9rem 0; padding: .6rem .9rem; border: 1px solid #cbd5e1; border-radius: 6px; }
    .ms-head .v { font-weight: 700; color: #16233a; }
    .list { width: 100%; border-collapse: collapse; }
    .list th { text-align: left; font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; color: #4b5563; border-bottom: 2px solid #1e3a5f; padding: .35rem .45rem; }
    .list td { padding: .42rem .45rem; border-bottom: 1px solid #e5e7eb; }
    .list .n { width: 1.75rem; color: #6b7280; }
    .list .score { width: 5.5rem; text-align: center; font-weight: 700; }
    .list .box { border: 1px solid #9ca3af; height: 1.35rem; }
    .list .comment { width: 35%; }
    .sign { display: grid; grid-template-columns: repeat(2, 1fr); gap: 2rem; margin-top: 2rem; }
    .sign div { border-top: 1px solid #111827; padding-top: .3rem; font-size: .75rem; color: #4b5563; }
@endsection

@section('content')
    <div class="sheet">
        <div class="letterhead">
            @if ($logo)
                <img src="{{ $logo }}" alt="">
            @endif
            <div class="who">
                <h1>{{ $school?->name }}</h1>
                <p>Mark sheet — {{ $assessment->name }}, {{ $assessment->term?->label() }}</p>
            </div>
        </div>

        <div class="ms-head">
            <div><div class="label">Subject</div><div class="v">{{ $subject->name }}</div></div>
            <div><div class="label">Class</div><div class="v">{{ $class->name }}{{ $section ? ' · '.$section->name : '' }}</div></div>
            <div><div class="label">Marked out of</div><div class="v">{{ $max + 0 }}</div></div>
            <div><div class="label">Teacher</div><div class="v">{{ $teacher ?? '—' }}</div></div>
            <div><div class="label">Learners</div><div class="v">{{ $students->count() }}</div></div>
            <div>
                <div class="label">Status</div>
                <div class="v">{{ $blank ? 'Blank sheet' : $sheet->statusLabel().' · '.$entered->count().' of '.$students->count().' entered' }}</div>
            </div>
        </div>

        <table class="list">
            <thead>
                <tr>
                    <th class="n">#</th>
                    <th>Adm. No.</th>
                    <th>Name</th>
                    <th class="score">Score /{{ $max + 0 }}</th>
                    <th class="comment">Comment</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($students as $i => $student)
                    @php($mark = $marks->get($student->id))
                    <tr>
                        <td class="n">{{ $i + 1 }}</td>
                        <td>{{ $student->admission_no }}</td>
                        <td>{{ $student->name }}</td>
                        @if ($blank)
                            <td class="score"><div class="box"></div></td>
                            <td class="comment"></td>
                        @else
                            <td class="score">{{ $mark?->is_absent ? 'AB' : $n($mark?->score) }}</td>
                            <td class="comment">{{ $mark?->comment }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="sign">
            <div>Subject teacher's signature &amp; date</div>
            <div>Director of Studies' signature &amp; date</div>
        </div>
    </div>
@endsection
