{{-- Phones only: the bottom bar (App\Support\MobileNav) and the "add to home screen" tip. --}}
@php($items = \App\Support\MobileNav::items())

@if ($items)
    <nav class="sh-mnav" aria-label="Quick links">
        @foreach ($items as $item)
            <a href="{{ $item['url'] }}" @class(['sh-mnav-item', 'is-active' => $item['active']])>
                <x-filament::icon :icon="$item['icon']" class="sh-mnav-icon" />
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
        <button type="button" class="sh-mnav-item" x-data x-on:click="$store.sidebar.open()">
            <x-filament::icon icon="heroicon-o-bars-3" class="sh-mnav-icon" />
            <span>Menu</span>
        </button>
    </nav>

    <div class="sh-install" x-data="shInstallTip" x-show="show" x-cloak x-transition>
        <img src="{{ route('filament.app.app.icon', ['size' => 192]) }}" alt="">
        <div class="sh-install-text">
            <strong>Put SchoolHub on your home screen</strong>
            <span x-show="canPrompt">It opens like an app, straight to your school.</span>
            <span x-show="!canPrompt">Tap <b>Share</b> <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2.5l3.5 3.5-1 1L11 5.5V13H9V5.5L7.5 7l-1-1L10 2.5zM4 9h3v1.5H5.5v6h9v-6H13V9h3v9H4V9z"/></svg> then <b>Add to Home Screen</b>.</span>
        </div>
        <button type="button" class="sh-install-go" x-show="canPrompt" x-on:click="install()">Install</button>
        <button type="button" class="sh-install-x" x-on:click="dismiss()" aria-label="Not now">&times;</button>
    </div>
@endif
