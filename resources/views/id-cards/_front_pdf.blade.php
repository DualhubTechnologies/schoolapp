{{-- One card face, table-based markup so dompdf lays it out reliably. --}}
@php($student = $card['student'])
<table class="p-card">
    <tr>
        <td colspan="2" class="p-band">
            <table class="p-band-inner">
                <tr>
                    @if ($card['logoPath'])
                        <td class="p-logo-cell"><img class="p-logo" src="{{ $card['logoPath'] }}"></td>
                    @endif
                    <td>
                        <div class="p-school-name">{{ $card['school']->name ?? 'School' }}</div>
                        @if ($card['school']->motto ?? null)
                            <div class="p-school-sub">{{ $card['school']->motto }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="p-photo-cell">
            @if ($card['photoPath'])
                <img class="p-photo" src="{{ $card['photoPath'] }}">
            @else
                <div class="p-photo p-photo-fallback">{{ strtoupper(substr($student->name ?: '?', 0, 1)) }}</div>
            @endif
        </td>
        <td class="p-info-cell">
            <div class="p-name">{{ $student->name ?: 'No name' }}</div>
            <div class="p-line"><b>{{ $student->admission_no ?: '—' }}</b></div>
            <div class="p-line">{{ $student->schoolClass->name ?? '—' }}{{ $student->section ? ' · ' . $student->section->name : '' }}</div>
            <div class="p-line">
                {{ \App\Models\Student::GENDERS[$student->gender] ?? '—' }}
                @if ($student->date_of_birth) · DOB {{ $student->date_of_birth->format('d/m/Y') }} @endif
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="2" class="p-foot">
            <table class="p-band-inner">
                <tr>
                    <td class="p-foot-left">{{ $card['validYear'] ? 'Valid ' . $card['validYear'] : '' }}</td>
                    <td class="p-foot-right">{{ $card['school']->phone ?? '' }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
