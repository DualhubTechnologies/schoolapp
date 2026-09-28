{{-- Classic (landscape) back. $card: one entry from IdCardService::cardsFor(). --}}
@php
    $student = $card['student'];
    $school = $card['school'];
    $guardian = $card['guardian'];
    $relationship = $guardian?->relationship ? (\App\Models\Guardian::RELATIONSHIPS[$guardian->relationship] ?? $guardian->relationship) : null;
@endphp
<div class="idc idc-classic">
    <div class="c-back-head">
        <div class="c-back-kicker">If found, please return to</div>
        <div class="c-back-school">{{ Str::limit($school?->name ?? '', 52) }}</div>
    </div>
    <div class="idc-rule"></div>

    <div class="c-back-body">
        <table class="c-row">
            <tr>
                <td class="c-row-label"><div class="idc-label">Parent / guardian</div></td>
                <td><div class="idc-value">{{ Str::limit($guardian?->name ?? '—', 34) }}{{ $relationship ? ' ('.$relationship.')' : '' }}</div></td>
            </tr>
            <tr>
                <td class="c-row-label"><div class="idc-label">Telephone</div></td>
                <td><div class="idc-value">{{ $guardian?->phone ?? '—' }}{{ $guardian?->alt_phone ? ' / '.$guardian->alt_phone : '' }}</div></td>
            </tr>
            <tr>
                <td class="c-row-label"><div class="idc-label">Home address</div></td>
                <td><div class="idc-value">{{ Str::limit($student->address ?? '—', 60) }}</div></td>
            </tr>
            @if ($student->medical_notes)
                <tr>
                    <td class="c-row-label"><div class="idc-label">Medical</div></td>
                    <td><div class="idc-value">{{ Str::limit($student->medical_notes, 60) }}</div></td>
                </tr>
            @endif
        </table>
    </div>

    <div class="c-back-foot">
        <table>
            <tr>
                <td>
                    <div class="c-contact">
                        @if ($school?->address){{ Str::limit($school->address, 50) }}<br>@endif
                        @if ($school?->phone)Tel: {{ $school->phone }}<br>@endif
                        @if ($school?->email){{ $school->email }}@endif
                    </div>
                    <div class="c-property">This card is the property of the school and must be returned on request.</div>
                </td>
                <td class="c-sign-cell">
                    <div class="idc-sign">
                        @if ($card['signaturePath'])
                            <img src="{{ $card['signaturePath'] }}" alt="">
                        @endif
                        <div class="idc-sign-line">Head Teacher</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</div>
