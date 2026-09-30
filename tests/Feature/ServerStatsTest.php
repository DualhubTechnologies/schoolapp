<?php

use App\Filament\App\Widgets\PlatformServerKpis;
use App\Filament\Pages\SystemHealth as SystemHealthPage;
use App\Models\User;
use App\Support\ServerStats;
use App\Support\SystemHealth;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

/**
 * A fake /proc with the given readings: two CPU cores, memory and swap in
 * kB, and an uptime of 2 days 6 hours (the server this was written for).
 */
function fakeProc(string $loadavg, int $memTotal, int $memAvailable, int $swapTotal = 2621440, int $swapFree = 2585600): string
{
    $dir = storage_path('framework/testing/server-stats/proc-'.uniqid());
    File::ensureDirectoryExists($dir);
    File::put("{$dir}/loadavg", "{$loadavg} 1/300 12345\n");
    File::put("{$dir}/cpuinfo", "processor\t: 0\nmodel name\t: x\n\nprocessor\t: 1\nmodel name\t: x\n");
    File::put("{$dir}/meminfo", "MemTotal:       {$memTotal} kB\nMemFree:         191488 kB\nMemAvailable:   {$memAvailable} kB\nSwapTotal:      {$swapTotal} kB\nSwapFree:       {$swapFree} kB\n");
    File::put("{$dir}/uptime", "195360.12 380000.50\n");

    return $dir;
}

function reading(ServerStats $stats, string $key): array
{
    return collect($stats->readings())->firstWhere('key', $key);
}

afterEach(fn () => File::deleteDirectory(storage_path('framework/testing/server-stats')));

it('reads a quiet server as fine', function () {
    // The readings from the live server: idle, 741 MB of 1.9 GB free, 35 MB swap.
    $stats = new ServerStats(fakeProc('0.00 0.03 0.02', 1992294, 758784));

    expect(reading($stats, 'load'))->toMatchArray(['status' => 'ok', 'value' => '0.00 · 0.03 · 0.02'])
        ->and(reading($stats, 'load')['detail'])->toContain('on 2 cores')
        ->and(reading($stats, 'memory'))->toMatchArray(['status' => 'ok', 'value' => '741 MB available of 1.9 GB', 'percent' => 62])
        ->and(reading($stats, 'swap')['status'])->toBe('ok')
        ->and(reading($stats, 'uptime')['value'])->toBe('2 days, 6 hours')
        ->and(reading($stats, 'database')['status'])->toBe('ok')
        ->and($stats->summaryCheck())->toMatchArray(['label' => 'Server', 'status' => 'ok']);
});

it('flags a busy server, short of memory, with what to do', function () {
    $stats = new ServerStats(fakeProc('2.40 2.10 1.90', 1992294, 150000, 2621440, 800000));

    expect(reading($stats, 'load')['status'])->toBe('danger')
        ->and(reading($stats, 'memory')['status'])->toBe('danger')
        ->and(reading($stats, 'memory')['fix'])->toContain('more RAM')
        ->and(reading($stats, 'swap')['status'])->toBe('danger')
        ->and($stats->summaryCheck()['status'])->toBe('danger');
});

it('leaves out what it cannot read', function () {
    $stats = new ServerStats(storage_path('framework/testing/server-stats/no-such-proc'));
    $keys = collect($stats->readings())->pluck('key');

    expect($keys)->not->toContain('memory')
        ->not->toContain('swap')
        ->not->toContain('uptime')
        ->toContain('database');
});

it('shows the server on System health and the dashboard for the platform owner', function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('admin');
    app()->instance(ServerStats::class, new ServerStats(fakeProc('0.10 0.20 0.30', 1992294, 758784)));
    $this->actingAs(User::factory()->create()->assignRole('Super Admin'));

    Livewire::test(SystemHealthPage::class)
        ->assertOk()
        ->assertSee(['Server', 'CPU load', '741 MB available of 1.9 GB', '2 days, 6 hours', 'Refresh now']);

    expect(collect(app(SystemHealth::class)->checks())->firstWhere('label', 'Server')['status'])->toBe('ok');

    Livewire::test(PlatformServerKpis::class)
        ->assertSee(['CPU load', 'Memory', 'Disk', 'Database']);
});
