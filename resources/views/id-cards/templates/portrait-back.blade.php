{{-- Portrait (upright) back. $card: one entry from IdCardService::cardsFor(). --}}
@php
    $student = $card['student'];
    $school = $card['school'];
    $guardian = $card['guardian'];
    $relationship = $guardian?->relationship ? (\App\Models\Guardian::RELATIONSHIPS[$guardian->relationship] ?? $guardian->relationship) : null;
@endphp
<div class="idc idc-portrait">
    <div class="p-back-head">
        <div class="p-back-title">Student Identity Card</div>
        <div class="p-back-school">{{ Str::limit($school?->name ?? '', 44) }}</div>
    </div>
    <div class="idc-rule"></div>

    <div class="p-back-body">
        <div class="p-return">
            <b>If found, please return to the school.</b><br>
            @if ($school?->address){{ Str::limit($school->address, 50) }}<br>@endif
            @if ($school?->phone)Tel: {{ $school->phone }}@endif
        </div>

        <div class="p-block">
            <div class="idc-label">Parent / guardian</div>
            <div class="idc-value">{{ Str::limit($guardian?->name ?? '—', 30) }}{{ $relationship ? ' ('.$relationship.')' : '' }}</div>
        </div>
        <div class="p-block">
            <div class="idc-label">Telephone</div>
            <div class="idc-value">{{ $guardian?->phone ?? '—' }}{{ $guardian?->alt_phone ? ' / '.$guardian->alt_phone : '' }}</div>
        </div>
        <div class="p-block">
            <div class="idc-label">Home address</div>
            <div class="idc-value">{{ Str::limit($student->address ?? '—', 48) }}</div>
        </div>
        @if ($student->medical_notes)
            <div class="p-block">
                <div class="idc-label">Medical</div>
                <div class="idc-value">{{ Str::limit($student->medical_notes, 48) }}</div>
            </div>
        @endif
    </div>

    <div class="p-sign">
        <div class="idc-sign">
            @if ($card['signaturePath'])
                <img src="{{ $card['signaturePath'] }}" alt="">
            @endif
            <div class="idc-sign-line">Head Teacher</div>
        </div>
    </div>
    <div class="p-property">This card is the property of the school and must be returned on request.</div>
    <div class="p-back-foot"></div>
</div>
