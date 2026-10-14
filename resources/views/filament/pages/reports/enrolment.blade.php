@php
    $rows = $this->rows();
    $residencies = $this->residencies();
    $total = collect($rows)->firstWhere('kind', 'total');
@endphp

<x-filament-panels::page>
    <x-report.toolbar>
        <x-report.field label="Learners">
            <x-filament::input.select wire:model.live="status">
                @foreach (\App\Models\Student::STATUSES as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-report.field>
        @if ($this->levelOptions())
            <x-report.field label="Section of the school">
                <x-filament::input.select wire:model.live="levelId">
                    <option value="">Whole school</option>
                    @foreach ($this->levelOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-report.field>
        @endif
    </x-report.toolbar>

    <x-report.paper :title="$this->getTitle()" :coverage="$this->coverage()">
        @if (! $total)
            <p class="rp-empty">No learners to count.</p>
        @else
            <div class="rp-tiles">
                <div><span>Learners</span><b>{{ number_format($total['total']) }}</b></div>
                <div><span>Boys</span><b>{{ number_format($total['boys']) }}</b></div>
                <div><span>Girls</span><b>{{ number_format($total['girls']) }}</b></div>
                @foreach ($residencies as $id => $name)
                    <div><span>{{ $name }}</span><b>{{ number_format($total['residency'][$id] ?? 0) }}</b></div>
                @endforeach
            </div>

            <div class="rp-scroll">
                <table class="rp-table">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th class="rp-num">Boys</th>
                            <th class="rp-num">Girls</th>
                            <th class="rp-num">Total</th>
                            @foreach ($residencies as $name)
                                <th class="rp-num">{{ $name }}</th>
                            @endforeach
                            <th class="rp-num">New</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr @class(['rp-sub' => $row['kind'] === 'stream', 'rp-total' => $row['kind'] === 'total'])>
                                <td>{{ $row['label'] }}</td>
                                <td class="rp-num">{{ $row['boys'] }}</td>
                                <td class="rp-num">{{ $row['girls'] }}</td>
                                <td class="rp-num rp-strong">{{ $row['total'] }}</td>
                                @foreach ($residencies as $id => $name)
                                    <td class="rp-num">{{ $row['residency'][$id] ?? 0 }}</td>
                                @endforeach
                                <td class="rp-num">{{ $row['new'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="rp-note">Streams are listed under their class. "New" counts learners admitted as new to the school.</p>
        @endif
    </x-report.paper>
</x-filament-panels::page>
