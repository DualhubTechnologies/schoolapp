{{-- $card: one entry from IdCardService::cardsFor() --}}
@php($student = $card['student'])
<div class="idc-card">
    <div class="idc-band">
        @if ($card['logoPath'])
            <img src="{{ $card['logoPath'] }}" alt="">
        @endif
        <div class="idc-band-text">
            <div class="idc-school-name">{{ $card['school']->name ?? 'School' }}</div>
            @if ($card['school']->motto ?? null)
                <div class="idc-school-sub">{{ $card['school']->motto }}</div>
            @endif
        </div>
    </div>

    <div class="idc-front-body">
        @if ($card['photoPath'])
            <img class="idc-photo" src="{{ $card['photoPath'] }}" alt="">
        @else
            <div class="idc-photo idc-photo-fallback">{{ strtoupper(substr($student->name ?: '?', 0, 1)) }}</div>
        @endif
        <div class="idc-front-info">
            <div class="idc-name">{{ $student->name ?: 'No name' }}</div>
            <div class="idc-line"><b>{{ $student->admission_no ?: '—' }}</b></div>
            <div class="idc-line">{{ $student->schoolClass->name ?? '—' }}{{ $student->section ? ' · ' . $student->section->name : '' }}</div>
            <div class="idc-line">
                {{ \App\Models\Student::GENDERS[$student->gender] ?? '—' }}
                @if ($student->date_of_birth) · DOB {{ $student->date_of_birth->format('d/m/Y') }} @endif
            </div>
        </div>
    </div>

    <div class="idc-front-foot">
        <span>{{ $card['validYear'] ? 'Valid ' . $card['validYear'] : '' }}</span>
        <span>{{ $card['school']->phone ?? '' }}</span>
    </div>
</div>
