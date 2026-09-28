{{-- The school's contact lines for the back. --}}
@php($school = $shared['school'])
@if ($school->address){{ Str::limit($school->address, 60) }}<br>@endif
@if ($school->phone)<b>Tel:</b> {{ $school->phone }}<br>@endif
@if ($school->email)<b>Email:</b> {{ $school->email }}<br>@endif
@if ($school->website)<b>Web:</b> {{ Str::limit($school->website, 40) }}@endif
