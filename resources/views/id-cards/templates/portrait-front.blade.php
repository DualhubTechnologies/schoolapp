{{-- Portrait (upright) front. $card: one entry from IdCardService::cardsFor(). --}}
@php
    $student = $card['student'];
    $school = $card['school'];
@endphp
<div class="idc idc-portrait">
    <div class="p-head">
        @if ($card['logoPath'])
            <div class="idc-crest"><img src="{{ $card['logoPath'] }}" alt=""></div>
        @endif
        <div class="p-school">{{ Str::limit($school?->name ?? '', 48) }}</div>
        @if ($school?->motto)
            <div class="p-motto">{{ Str::limit($school->motto, 48) }}</div>
        @endif
    </div>
    <div class="idc-rule"></div>

    <div class="p-photo-wrap">
        <div class="idc-photo">
            @if ($card['photoPath'])
                <img src="{{ $card['photoPath'] }}" alt="">
            @else
                <div class="idc-initial">{{ Str::upper(Str::substr($student->name ?: '?', 0, 1)) }}</div>
            @endif
        </div>
    </div>

    <div class="p-name">{{ Str::limit(Str::upper($student->name ?? ''), 40) }}</div>
    <div class="p-class"><span>{{ Str::limit(($student->schoolClass?->name ?? '—').($student->section ? ' · '.$student->section->name : ''), 22) }}</span></div>

    <div class="p-fields">
        <table>
            <tr>
                <td><div class="idc-label">Adm. No.</div></td>
                <td><div class="idc-value">{{ $student->admission_no ?: '—' }}</div></td>
            </tr>
            <tr>
                <td><div class="idc-label">Date of birth</div></td>
                <td><div class="idc-value">{{ $student->date_of_birth?->format('d M Y') ?? '—' }}</div></td>
            </tr>
            <tr>
                <td><div class="idc-label">Sex</div></td>
                <td><div class="idc-value">{{ \App\Models\Student::GENDERS[$student->gender] ?? '—' }}</div></td>
            </tr>
        </table>
    </div>

    <div class="p-foot">
        <table>
            <tr>
                <td>Student ID @if ($card['validYear'])<span>&nbsp;·&nbsp; Valid {{ $card['validYear'] }}</span>@endif</td>
            </tr>
        </table>
    </div>
</div>
