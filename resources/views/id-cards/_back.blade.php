{{-- $card: one entry from IdCardService::cardsFor() --}}
@php($student = $card['student'])
@php($guardian = $card['guardian'])
<div class="idc-card">
    <div class="idc-band">
        <div class="idc-band-text">
            <div class="idc-school-name">If found, please return to</div>
            <div class="idc-school-sub">{{ $card['school']->name ?? 'School' }} @if ($card['school']->phone ?? null) · {{ $card['school']->phone }} @endif</div>
        </div>
    </div>

    <div class="idc-back-body">
        <div class="idc-back-title">Parent / guardian</div>
        {{ $guardian?->name ?? '—' }}{{ $guardian?->relationship ? ' (' . (\App\Models\Guardian::RELATIONSHIPS[$guardian->relationship] ?? $guardian->relationship) . ')' : '' }}
        @if ($guardian?->phone) · {{ $guardian->phone }} @endif

        <div class="idc-back-title">Home address</div>
        {{ $student->address ?: '—' }}

        @if ($student->medical_notes)
            <div class="idc-back-title">Medical notes</div>
            {{ $student->medical_notes }}
        @endif
    </div>

    @if ($card['signaturePath'])
        <div class="idc-signature">
            <img src="{{ $card['signaturePath'] }}" alt="">
            <div class="idc-signature-label">Head Teacher</div>
        </div>
    @endif

    <div class="idc-fine-print">This card remains the property of {{ $card['school']->name ?? 'the school' }} and must be surrendered on request.</div>
</div>
