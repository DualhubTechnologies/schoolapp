{{-- Classic (landscape) front. $card: one entry from IdCardService::cardsFor(). --}}
@php
    $student = $card['student'];
    $school = $card['school'];
@endphp
<div class="idc idc-classic">
    <div class="c-head">
        <table>
            <tr>
                @if ($card['logoPath'])
                    <td class="c-crest-cell"><div class="idc-crest"><img src="{{ $card['logoPath'] }}" alt=""></div></td>
                @endif
                <td>
                    <div class="c-school">{{ Str::limit($school?->name ?? '', 48) }}</div>
                    @if ($school?->motto)
                        <div class="c-motto">{{ Str::limit($school->motto, 60) }}</div>
                    @endif
                </td>
                <td class="c-tag-cell"><div class="c-tag">Student<br>ID Card</div></td>
            </tr>
        </table>
    </div>
    <div class="idc-rule"></div>

    <div class="c-body">
        <table>
            <tr>
                <td class="c-photo-cell">
                    <div class="idc-photo">
                        @if ($card['photoPath'])
                            <img src="{{ $card['photoPath'] }}" alt="">
                        @else
                            <div class="idc-initial">{{ Str::upper(Str::substr($student->name ?: '?', 0, 1)) }}</div>
                        @endif
                    </div>
                </td>
                <td>
                    <div class="c-name">{{ Str::limit(Str::upper($student->name ?? ''), 44) }}</div>
                    <div class="c-divider"></div>
                    <table class="c-fields">
                        <tr>
                            <td>
                                <div class="idc-label">Adm. No.</div>
                                <div class="idc-value">{{ $student->admission_no ?: '—' }}</div>
                            </td>
                            <td>
                                <div class="idc-label">Class</div>
                                <div class="idc-value">{{ Str::limit(($student->schoolClass?->name ?? '—').($student->section ? ' '.$student->section->name : ''), 18) }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="idc-label">Date of birth</div>
                                <div class="idc-value">{{ $student->date_of_birth?->format('d M Y') ?? '—' }}</div>
                            </td>
                            <td>
                                <div class="idc-label">Sex</div>
                                <div class="idc-value">{{ \App\Models\Student::GENDERS[$student->gender] ?? '—' }}</div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <div class="c-foot">
        <table>
            <tr>
                <td class="c-foot-title">Student Identity Card</td>
                <td class="c-valid">
                    @if ($card['validYear'])
                        <span>Valid</span> {{ $card['validYear'] }}
                    @endif
                </td>
            </tr>
        </table>
    </div>
</div>
