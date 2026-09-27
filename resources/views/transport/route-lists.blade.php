@extends('fees.layout')

@php
    $logo = $school?->logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($school->logo) : null;
@endphp

@section('title', 'Van route lists')

@section('styles')
    @page { size: A4; margin: 12mm; }
    .sheet { max-width: 210mm; padding: 1.75rem 2rem; font-size: 13px; }
    .route-head { display: flex; justify-content: space-between; gap: 1rem; margin: 1rem 0; padding: .75rem 1rem; background: #f1f5fb; border-left: 4px solid #1e3a5f; }
    .route-head h2 { font-size: 1.25rem; font-weight: 800; color: #1e3a5f; }
    .route-head p { margin: .15rem 0 0; color: #374151; }
    .list { width: 100%; border-collapse: collapse; }
    .list th { text-align: left; font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #4b5563; border-bottom: 2px solid #1e3a5f; padding: .4rem .5rem; }
    .list td { padding: .45rem .5rem; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
    .list .n { width: 1.75rem; color: #6b7280; }
    .tick { width: 2.5rem; border: 1px solid #9ca3af; }
    .paid { color: #15803d; font-weight: 700; }
    .owes { color: #b91c1c; font-weight: 700; }
    .muted { color: #6b7280; }
    .foot { margin-top: 1.25rem; display: flex; justify-content: space-between; color: #4b5563; font-size: .8rem; }
@endsection

@section('content')
    @foreach ($routes as $route)
        <div class="sheet">
            <div class="letterhead">
                @if ($logo)
                    <img src="{{ $logo }}" alt="">
                @endif
                <div class="who">
                    <h1>{{ $school?->name }}</h1>
                    <p>School van route list{{ $term ? ' — '.$term->label() : '' }}</p>
                </div>
            </div>

            <div class="route-head">
                <div>
                    <h2>{{ $route->name }}</h2>
                    <p>{{ $route->students->count() }} {{ \Illuminate\Support\Str::plural('learner', $route->students->count()) }}{{ $route->capacity ? ' · '.$route->capacity.' seats' : '' }}</p>
                </div>
                <div style="text-align: right">
                    <p><strong>{{ $route->vehicle ?: 'Van: —' }}</strong></p>
                    <p>{{ $route->driver_name ?: 'Driver: —' }}{{ $route->driver_phone ? ' · '.$route->driver_phone : '' }}</p>
                </div>
            </div>

            @if ($route->students->isEmpty())
                <p class="muted">No learners on this route.</p>
            @else
                <table class="list">
                    <thead>
                        <tr><th class="n">#</th><th>Learner</th><th>Class</th><th>Uses the van</th><th>Parent / guardian</th><th>Van fee</th><th>✓</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($route->students as $i => $student)
                            @php($s = $status[$student->id])
                            <tr>
                                <td class="n">{{ $i + 1 }}</td>
                                <td><strong>{{ $student->name }}</strong><br><span class="muted">{{ $student->admission_no }}</span></td>
                                <td>{{ $student->schoolClass?->name }}{{ $student->section ? ' · '.$student->section->name : '' }}</td>
                                <td>{{ \App\Models\TransportRoute::TRIPS[$student->transport_trip] ?? 'Both ways' }}</td>
                                <td>{{ $student->guardian?->name ?? '—' }}<br><span class="muted">{{ $student->guardian?->phone }}</span></td>
                                <td>
                                    @if ($s['label'] === 'Paid')
                                        <span class="paid">Paid</span>
                                    @elseif ($s['label'] === 'Owes')
                                        <span class="owes">Owes {{ number_format($s['owed']) }}</span>
                                    @else
                                        <span class="muted">Not billed</span>
                                    @endif
                                </td>
                                <td class="tick"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <div class="foot">
                <span>Printed {{ now()->format('j M Y, H:i') }}</span>
                <span>Driver's signature: ______________________</span>
            </div>
        </div>
    @endforeach
@endsection
