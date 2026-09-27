{{-- "Who changed this?" on edit pages (EditRecordPage). --}}
@php
    $show = function ($value): string {
        if ($value === null || $value === '') return '—';
        if (is_bool($value)) return $value ? 'Yes' : 'No';
        if (is_array($value)) return json_encode($value);
        return \Illuminate\Support\Str::limit((string) $value, 60);
    };
    $label = fn (string $field): string => ucfirst(str_replace(['_id', '_'], ['', ' '], $field));
    $verb = ['created' => 'Created', 'updated' => 'Changed', 'deleted' => 'Deleted'];
@endphp

@if ($activities->isEmpty())
    <p class="sh-hist-empty">No changes recorded yet.</p>
@else
    <ol class="sh-hist">
        @foreach ($activities as $activity)
            @php
                $new = $activity->attribute_changes['attributes'] ?? [];
                $old = $activity->attribute_changes['old'] ?? [];
                $fields = collect(array_keys($new))->reject(fn ($f) => in_array($f, ['updated_at', 'created_at', 'id', 'school_id', 'password', 'remember_token'], true));
            @endphp
            <li>
                <div class="sh-hist-head">
                    <strong>{{ $verb[$activity->event] ?? ucfirst((string) $activity->description) }}</strong>
                    by {{ $activity->causer?->name ?? 'the system' }}
                    <span title="{{ $activity->created_at?->format('j M Y, g:i a') }}">· {{ $activity->created_at?->diffForHumans() }}</span>
                </div>
                @if ($activity->event === 'updated' && $fields->isNotEmpty())
                    <ul class="sh-hist-changes">
                        @foreach ($fields as $field)
                            <li><span>{{ $label($field) }}</span> {{ $show($old[$field] ?? null) }} → <b>{{ $show($new[$field] ?? null) }}</b></li>
                        @endforeach
                    </ul>
                @endif
            </li>
        @endforeach
    </ol>
@endif

<style>
    .sh-hist { list-style: none; margin: 0; padding: 0 0 0 1rem; border-left: 2px solid #e4e9f1; display: grid; gap: .9rem; }
    .sh-hist > li { position: relative; }
    .sh-hist > li::before { content: ""; position: absolute; left: -1.38rem; top: .35rem; width: .6rem; height: .6rem; border-radius: 50%; background: #1a5fa8; box-shadow: 0 0 0 3px #fff; }
    .sh-hist-head { font-size: .88rem; color: #334155; }
    .sh-hist-head span { color: #94a3b8; }
    .sh-hist-changes { margin: .35rem 0 0; padding: 0; list-style: none; font-size: .82rem; color: #475569; display: grid; gap: .15rem; }
    .sh-hist-changes span { display: inline-block; min-width: 8rem; color: #94a3b8; }
    .sh-hist-empty { color: #64748b; font-size: .9rem; }
</style>
