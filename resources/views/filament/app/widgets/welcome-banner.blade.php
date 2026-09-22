@php
    $term = $this->termProgress();
    $actions = $this->actions();
    $school = auth()->user()?->school;
@endphp

<x-filament-widgets::widget>
    <div class="shd-banner">
        <div class="shd-banner-main">
            <span class="shd-banner-chip">{{ $this->profileLabel() }}</span>
            <h2 class="shd-banner-title">{{ $this->greeting() }}, {{ $this->firstName() }}</h2>
            <p class="shd-banner-meta">
                @if ($school){{ $school->name }} · @endif{{ now()->format('l, j F Y') }}
            </p>

            @if ($actions)
                <div class="shd-banner-actions">
                    @foreach ($actions as $action)
                        <a href="{{ $action['url'] }}" @class(['shd-banner-btn', 'is-primary' => $action['primary']])>
                            <x-filament::icon :icon="$action['icon']" class="shd-banner-btn-icon" />
                            {{ $action['label'] }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($term)
            <div class="shd-term">
                <div class="shd-term-label">Current term</div>
                <div class="shd-term-name">{{ $term['label'] }}</div>
                @if ($term['weeks'])
                    <div class="shd-term-bar" role="progressbar" aria-valuenow="{{ $term['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Term progress">
                        <span style="width: {{ $term['percent'] }}%"></span>
                    </div>
                    <div class="shd-term-foot">
                        <span>Week {{ $term['week'] }} of {{ $term['weeks'] }}</span>
                        <span>{{ $term['daysLeft'] }} {{ str('day')->plural($term['daysLeft']) }} left</span>
                    </div>
                @else
                    <div class="shd-term-foot"><span>Add the term's start and end dates to track progress.</span></div>
                @endif
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
