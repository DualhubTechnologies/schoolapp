{{-- One card face, table-based markup so dompdf lays it out reliably. --}}
@php($student = $card['student'])
@php($guardian = $card['guardian'])
<table class="p-card">
    <tr>
        <td class="p-band">
            <div class="p-school-name">If found, please return to</div>
            <div class="p-school-sub">{{ $card['school']->name ?? 'School' }}@if ($card['school']->phone ?? null) · {{ $card['school']->phone }} @endif</div>
        </td>
    </tr>
    <tr>
        <td class="p-back-body">
            <div class="p-back-title">Parent / guardian</div>
            <div>{{ $guardian?->name ?? '—' }}{{ $guardian?->relationship ? ' (' . (\App\Models\Guardian::RELATIONSHIPS[$guardian->relationship] ?? $guardian->relationship) . ')' : '' }}@if ($guardian?->phone) · {{ $guardian->phone }} @endif</div>

            <div class="p-back-title">Home address</div>
            <div>{{ $student->address ?: '—' }}</div>

            @if ($student->medical_notes)
                <div class="p-back-title">Medical notes</div>
                <div>{{ $student->medical_notes }}</div>
            @endif
        </td>
    </tr>
    <tr>
        <td class="p-sign-row">
            <table class="p-band-inner">
                <tr>
                    <td class="p-fine-print">This card remains the property of {{ $card['school']->name ?? 'the school' }} and must be surrendered on request.</td>
                    @if ($card['signaturePath'])
                        <td class="p-sign-cell">
                            <img class="p-sign" src="{{ $card['signaturePath'] }}">
                            <div class="p-sign-label">Head Teacher</div>
                        </td>
                    @endif
                </tr>
            </table>
        </td>
    </tr>
</table>
