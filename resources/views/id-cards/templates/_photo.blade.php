{{-- The learner's photo in the accent frame, or their initial when there is none. --}}
<div class="idc-photo">
    @if ($card['photoPath'])
        <img src="{{ $card['photoPath'] }}" alt="">
    @else
        <div class="idc-initial">{{ Str::upper(Str::substr($card['student']->name ?: '?', 0, 1)) }}</div>
    @endif
</div>
