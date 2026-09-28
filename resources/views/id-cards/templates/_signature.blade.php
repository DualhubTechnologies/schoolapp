{{-- The head teacher's signature over a line, or just the line to sign by hand. --}}
<div class="idc-sign">
    @if ($shared['signaturePath'])
        <img src="{{ $shared['signaturePath'] }}" alt="">
    @else
        <div style="height: 6.5mm;"></div>
    @endif
    <div class="idc-sign-line">Head Teacher</div>
</div>
