<x-filament-panels::page>
    <p class="rp-intro">Every report can be printed on A4 or downloaded for Excel. Pick one to open it.</p>

    @foreach (\App\Filament\Pages\Reports\AllReports::available() as $area => $reports)
        <section class="rp-area">
            <h3 class="rp-area-title">{{ $area }}</h3>
            <div class="rp-cards">
                @foreach ($reports as $report)
                    <a href="{{ $report['url'] }}" class="rp-card">
                        <span class="rp-card-icon"><x-filament::icon :icon="$report['icon']" /></span>
                        <span class="rp-card-body">
                            <b>{{ $report['title'] }}</b>
                            <span>{{ $report['about'] }}</span>
                        </span>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="rp-card-go" />
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</x-filament-panels::page>
