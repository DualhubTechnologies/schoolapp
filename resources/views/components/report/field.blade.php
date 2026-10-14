{{-- One filter on a report toolbar: a label over its input. --}}
@props(['label'])
<label class="rp-field">
    <span>{{ $label }}</span>
    <x-filament::input.wrapper>{{ $slot }}</x-filament::input.wrapper>
</label>
