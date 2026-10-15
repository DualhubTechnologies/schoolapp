<x-filament-panels::page>
    <p class="rpt-intro">Every report can be printed on A4 or downloaded for Excel. Pick one to open it.</p>

    @foreach (\App\Filament\Pages\Reports\AllReports::available() as $area => $reports)
        <section class="rpt-area">
            <h3 class="rpt-area-title">{{ $area }}</h3>
            <div class="rpt-cards">
                @foreach ($reports as $report)
                    <a href="{{ $report['url'] }}" class="rpt-card">
                        <span class="rpt-card-icon"><x-filament::icon :icon="$report['icon']" /></span>
                        <span class="rpt-card-body">
                            <b>{{ $report['title'] }}</b>
                            <span>{{ $report['about'] }}</span>
                        </span>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="rpt-card-go" />
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</x-filament-panels::page>
