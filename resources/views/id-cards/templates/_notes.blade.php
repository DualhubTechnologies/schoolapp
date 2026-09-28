{{-- The template's rules, one bulleted line each. --}}
<table class="idc-notes">
    @foreach (array_slice($shared['notes'], 0, 5) as $note)
        <tr>
            <td class="n-dot">•</td>
            <td>{{ Str::limit($note, 110) }}</td>
        </tr>
    @endforeach
</table>
