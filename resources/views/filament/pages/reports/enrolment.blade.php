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
            <p class="rpt-empty">No learners to count.</p>
        @else
            <div class="rpt-tiles">
                <div><span>Learners</span><b>{{ number_format($total['total']) }}</b></div>
                <div><span>Boys</span><b>{{ number_format($total['boys']) }}</b></div>
                <div><span>Girls</span><b>{{ number_format($total['girls']) }}</b></div>
                @foreach ($residencies as $id => $name)
                    <div><span>{{ $name }}</span><b>{{ number_format($total['residency'][$id] ?? 0) }}</b></div>
                @endforeach
            </div>

            <div class="rpt-scroll">
                <table class="rpt-table">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th class="rpt-num">Boys</th>
                            <th class="rpt-num">Girls</th>
                            <th class="rpt-num">Total</th>
                            @foreach ($residencies as $name)
                                <th class="rpt-num">{{ $name }}</th>
                            @endforeach
                            <th class="rpt-num">New</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr @class(['rpt-sub' => $row['kind'] === 'stream', 'rpt-total' => $row['kind'] === 'total'])>
                                <td>{{ $row['label'] }}</td>
                                <td class="rpt-num">{{ $row['boys'] }}</td>
                                <td class="rpt-num">{{ $row['girls'] }}</td>
                                <td class="rpt-num rpt-strong">{{ $row['total'] }}</td>
                                @foreach ($residencies as $id => $name)
                                    <td class="rpt-num">{{ $row['residency'][$id] ?? 0 }}</td>
                                @endforeach
                                <td class="rpt-num">{{ $row['new'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="rpt-note">Streams are listed under their class. "New" counts learners admitted as new to the school.</p>
        @endif
    </x-report.paper>
</x-filament-panels::page>
