@php
    $summary = $this->audienceSummary;
    $count = $summary['recipients']->count();
    $sample = $this->sample();
    $length = mb_strlen($this->body);
    $needsClass = $this->audience === 'class';
    $statusTone = ['queued' => 'is-wait', 'sending' => 'is-wait', 'done' => 'is-ok', 'failed' => 'is-bad'];
@endphp

<x-filament-panels::page>
    <div class="sm-grid">
        <section class="sm-card">
            <div class="sm-body">
                <label class="sm-label">Send to</label>
                <div class="sm-audiences">
                    @foreach (\App\Models\MessageBatch::AUDIENCES as $key => $label)
                        <label @class(['sm-audience', 'is-on' => $this->audience === $key])>
                            <input type="radio" wire:model.live="audience" value="{{ $key }}">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>

                @if (in_array($this->audience, ['class', 'owing'], true))
                    <div class="sm-pair">
                        <div>
                            <label class="sm-label">Class{{ $needsClass ? '' : ' (optional)' }}</label>
                            <x-filament::input.wrapper>
                                <x-filament::input.select wire:model.live="classId">
                                    <option value="">{{ $needsClass ? 'Choose a class…' : 'Every class' }}</option>
                                    @foreach ($this->classOptions() as $id => $label)
                                        <option value="{{ $id }}">{{ $label }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </div>
                        <div>
                            <label class="sm-label">Stream (optional)</label>
                            <x-filament::input.wrapper>
                                <x-filament::input.select wire:model.live="sectionId" :disabled="! $this->classId">
                                    <option value="">Whole class</option>
                                    @foreach ($this->sectionOptions() as $id => $label)
                                        <option value="{{ $id }}">{{ $label }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </div>
                    </div>
                @endif

                <label class="sm-label" for="sm-body">Message</label>
                <textarea id="sm-body" class="sm-text" rows="5" maxlength="{{ \App\Filament\Pages\SendMessages::MAX_LENGTH }}"
                          wire:model.live.debounce.600ms="body"
                          placeholder="e.g. Dear parent, the school will close on Friday at 1pm for the sports day. Pick up {student} by 1:30pm."></textarea>
                <div class="sm-hint">
                    <span>{{ $length }} / {{ \App\Filament\Pages\SendMessages::MAX_LENGTH }} characters · {{ $this->parts() }} SMS each</span>
                    @if ($this->audience !== 'staff')
                        <span>Personalise:
                            @foreach (\App\Services\Messaging\BulkMessages::PLACEHOLDERS as $tag => $what)
                                <button type="button" class="sm-tag" title="{{ $what }}" x-on:click="$wire.set('body', ($wire.body || '') + '{{ $tag }}')">{{ $tag }}</button>
                            @endforeach
                        </span>
                    @endif
                </div>
            </div>
        </section>

        <section class="sm-card">
            <div class="sm-body">
                <div class="sm-big">{{ $count }}</div>
                <div class="sm-muted">{{ $this->audience === 'staff' ? 'staff' : 'families' }} will receive it
                    @if ($summary['no_phone']) · <strong>{{ $summary['no_phone'] }}</strong> without a phone number @endif
                </div>
                @if ($this->audience !== 'staff' && ! app(\App\Services\Messaging\BulkMessages::class)->isPersonal($this->body))
                    <div class="sm-muted">Families with several children get one SMS.</div>
                @endif

                @if ($sample)
                    <label class="sm-label" style="margin-top: 1rem">Preview (first recipient)</label>
                    <div class="sm-phone">{{ $sample }}</div>
                @endif

                <div style="margin-top: 1rem">
                    @if ($needsClass && ! $this->classId)
                        <x-filament::button disabled>Choose a class first</x-filament::button>
                    @elseif (trim($this->body) === '')
                        <x-filament::button disabled>Write the message first</x-filament::button>
                    @else
                        {{ $this->sendAction }}
                    @endif
                </div>
            </div>
        </section>
    </div>

    <section class="sm-card">
        <div class="sm-head">Sent messages</div>
        @php($history = $this->history())
        @if ($history->isEmpty())
            <div class="sm-empty">Nothing sent yet.</div>
        @else
            <div class="sm-scroll" wire:poll.10s>
                <table class="sm-table">
                    <thead><tr><th>When</th><th>To</th><th>Message</th><th class="sm-c">Sent</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($history as $batch)
                            <tr>
                                <td class="sm-nowrap">{{ $batch->created_at->format('j M, g:i a') }}<div class="sm-muted">{{ $batch->sender?->name }}</div></td>
                                <td>{{ $batch->audience_label }}</td>
                                <td class="sm-msg">{{ \Illuminate\Support\Str::limit($batch->body, 120) }}</td>
                                <td class="sm-c">{{ $batch->sent }} / {{ $batch->recipients }}
                                    @if ($batch->failed)<div class="sm-muted">{{ $batch->failed }} failed</div>@endif
                                </td>
                                <td><span class="sm-pill {{ $statusTone[$batch->status] ?? '' }}">{{ ['queued' => 'Waiting', 'sending' => 'Sending', 'done' => 'Sent', 'failed' => 'Stopped'][$batch->status] ?? $batch->status }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <style>
        .sm-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 1024px) { .sm-grid { grid-template-columns: 3fr 2fr; } }
        .sm-card { background: #fff; border: 1px solid #e4e8f0; border-radius: 12px; overflow: hidden; }
        .sm-body { padding: 1.1rem 1.25rem; }
        .sm-head { padding: .85rem 1.25rem; font-weight: 700; color: #16233a; border-bottom: 1px solid #eef2f7; }
        .sm-label { display: block; font-size: .8rem; font-weight: 600; color: #374151; margin: .9rem 0 .35rem; }
        .sm-label:first-child { margin-top: 0; }
        .sm-audiences { display: grid; grid-template-columns: 1fr; gap: .4rem; }
        @media (min-width: 640px) { .sm-audiences { grid-template-columns: 1fr 1fr; } }
        .sm-audience { display: flex; align-items: center; gap: .5rem; padding: .55rem .75rem; border: 1px solid #e2e8f0; border-radius: 8px; font-size: .88rem; cursor: pointer; }
        .sm-audience.is-on { border-color: #2472c4; background: #f0f6fd; color: #1a5fa8; font-weight: 600; }
        .sm-pair { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
        .sm-text { width: 100%; padding: .6rem .75rem; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; font-size: .92rem; resize: vertical; }
        .sm-text:focus { outline: 2px solid #2472c4; border-color: #2472c4; }
        .sm-hint { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .4rem 1rem; margin-top: .4rem; font-size: .76rem; color: #64748b; }
        .sm-tag { margin-left: .25rem; padding: .05rem .4rem; border: 1px solid #cbd5e1; border-radius: 5px; background: #f8fafc; font-family: ui-monospace, monospace; font-size: .72rem; }
        .sm-big { font-size: 2rem; font-weight: 800; color: #16233a; line-height: 1; }
        .sm-muted { color: #64748b; font-size: .8rem; }
        .sm-phone { white-space: pre-wrap; background: #eef6ee; border-radius: 12px 12px 12px 2px; padding: .7rem .85rem; font-size: .88rem; color: #14301a; }
        .sm-empty { padding: 1.5rem; text-align: center; color: #64748b; }
        .sm-scroll { overflow-x: auto; }
        .sm-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        .sm-table th { text-align: left; font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; padding: .55rem .75rem; background: #f8fafc; border-bottom: 1px solid #e4e8f0; }
        .sm-table td { padding: .55rem .75rem; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        .sm-c { text-align: center !important; }
        .sm-nowrap { white-space: nowrap; }
        .sm-msg { min-width: 16rem; color: #374151; }
        .sm-pill { font-size: .72rem; font-weight: 700; padding: .15rem .55rem; border-radius: 999px; background: #e2e8f0; color: #334155; }
        .sm-pill.is-ok { background: #dcfce7; color: #166534; }
        .sm-pill.is-wait { background: #fef3c7; color: #92400e; }
        .sm-pill.is-bad { background: #fee2e2; color: #991b1b; }
    </style>
</x-filament-panels::page>
