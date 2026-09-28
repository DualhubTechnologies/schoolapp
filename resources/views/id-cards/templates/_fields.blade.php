{{-- The learner's details as "Label : value" rows. $card['fields'] from IdCardService::frontFields(). --}}
<table class="idc-fields">
    @foreach ($card['fields'] as $label => $value)
        <tr>
            <td class="f-label">{{ $label }}</td>
            <td class="f-colon">:</td>
            <td class="f-value">{{ Str::limit($value, 26) }}</td>
        </tr>
    @endforeach
</table>
