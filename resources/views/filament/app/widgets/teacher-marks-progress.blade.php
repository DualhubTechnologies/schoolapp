@php $rows = $this->rows(); @endphp

<x-filament-widgets::widget>
    <div class="shd-panel">
        <div class="shd-panel-head">
            <div>
                <h3 class="shd-panel-title">My mark sheets</h3>
                <p class="shd-panel-sub">
                    {{ collect($rows)->pluck('exam')->filter()->unique()->first() ?? 'No open exam this term' }}
                    · least complete first
                </p>
            </div>
        </div>

        @forelse ($rows as $row)
            @php
                $tone = $row['percent'] === null ? 'slate' : ($row['percent'] >= 100 ? 'emerald' : ($row['percent'] >= 50 ? 'amber' : 'rose'));
            @endphp
            <div class="shd-sheet">
                <div class="shd-sheet-name">
                    <strong>{{ $row['subject'] }}</strong>
                    <span>{{ $row['class'] }} · {{ $row['learners'] }} {{ str('learner')->plural($row['learners']) }}</span>
                </div>
                <div class="shd-sheet-progress shd-tone-{{ $tone }}">
                    <div class="shd-kpi-bar"><span style="width: {{ $row['percent'] ?? 0 }}%"></span></div>
                    <span class="shd-sheet-count">{{ $row['percent'] === null ? '—' : "{$row['entered']} / {$row['learners']}" }}</span>
                </div>
                <x-filament::button tag="a" :href="$row['url']" size="sm" :color="$row['percent'] >= 100 ? 'gray' : 'primary'" :outlined="$row['percent'] >= 100">
                    {{ $row['percent'] >= 100 ? 'Review' : 'Enter marks' }}
                </x-filament::button>
            </div>
        @empty
            <div class="shd-empty">
                <x-filament::icon icon="heroicon-o-book-open" class="shd-empty-icon" />
                <strong>No subjects assigned to you yet</strong>
                <span>The school administrator assigns teachers to class subjects under Academics → Classes.</span>
            </div>
        @endforelse
    </div>
</x-filament-widgets::widget>
