@php
    $rows = $this->rows();
    $total = collect($rows)->firstWhere('kind', 'total');
    $n = fn ($v) => number_format((float) $v);
@endphp

<x-filament-panels::page>
    <x-report.toolbar>
        <x-report.field label="Term">
            <x-filament::input.select wire:model.live="termId">
                @foreach ($this->termOptions() as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-report.field>
    </x-report.toolbar>

    <x-report.paper :title="$this->getTitle()" :coverage="$this->coverage()">
        @if (! $total)
            <p class="rp-empty">Nothing has been billed for this term yet. Bill the term from Fees → Billing.</p>
        @else
            <div class="rp-tiles">
                <div><span>Billed</span><b>UGX {{ $n($total['billed']) }}</b></div>
                <div class="is-good"><span>Collected</span><b>UGX {{ $n($total['paid']) }}</b></div>
                <div class="is-bad"><span>Still owed</span><b>UGX {{ $n(max(0, $total['balance'])) }}</b></div>
                <div class="is-main"><span>Collected so far</span><b>{{ $total['percent'] }}%</b></div>
            </div>

            <div class="rp-scroll">
                <table class="rp-table">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th class="rp-num">Learners</th>
                            <th class="rp-num">Billed</th>
                            <th class="rp-num">Paid</th>
                            <th class="rp-num">Balance</th>
                            <th>Collected</th>
                            <th class="rp-num">Owing</th>
                            <th class="rp-num">Cleared</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr @class(['rp-total' => $row['kind'] === 'total'])>
                                <td>{{ $row['label'] }}</td>
                                <td class="rp-num">{{ $row['learners'] }}</td>
                                <td class="rp-num">{{ $n($row['billed']) }}</td>
                                <td class="rp-num">{{ $n($row['paid']) }}</td>
                                <td @class(['rp-num', 'rp-strong', 'rp-bad' => $row['balance'] > 0])>{{ $n($row['balance']) }}</td>
                                <td class="rp-bar-cell">
                                    <span class="rp-bar"><i style="width: {{ $row['percent'] }}%"></i></span>
                                    <em>{{ $row['percent'] }}%</em>
                                </td>
                                <td class="rp-num">{{ $row['owing'] }}</td>
                                <td class="rp-num">{{ $row['cleared'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="rp-note">Billed is after discounts and bursaries. Paid counts payments received for this term. Each learner is counted in their current class.</p>
        @endif
    </x-report.paper>
</x-filament-panels::page>
