{{--
    A report as printed: the school's letterhead, the report's title and
    what it covers, the report itself (the slot), and when it was printed.
--}}
@props(['title', 'coverage' => null])

@php
    $school = auth()->user()?->school;
    $logo = $school?->logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($school->logo) : null;
    $contacts = collect([$school?->phone, $school?->email])->filter()->implode('  ·  ');
@endphp

<article class="rpt-paper">
    <header class="rpt-letterhead">
        @if ($logo)<img src="{{ $logo }}" alt="" class="rpt-logo">@endif
        <div class="rpt-school">
            <h2>{{ $school?->name }}</h2>
            @if ($school?->address)<p>{{ $school->address }}</p>@endif
            @if ($contacts)<p>{{ $contacts }}</p>@endif
        </div>
        <div class="rpt-doc">
            <h3>{{ $title }}</h3>
            @if ($coverage)<p>{{ $coverage }}</p>@endif
        </div>
    </header>

    {{ $slot }}

    <footer class="rpt-foot">Printed {{ now()->format('j M Y, g:i a') }} by {{ auth()->user()?->name }} · SchoolHub</footer>
</article>
