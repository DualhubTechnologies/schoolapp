<?php

use App\Console\Commands\BackupRun;
use App\Filament\Pages\SystemHealth as SystemHealthPage;
use App\Models\School;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use App\Support\SystemHealth;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuperAdminSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('admin');
});

function healthCheck(string $label): array
{
    return collect(app(SystemHealth::class)->checks())->firstWhere('label', $label);
}

it('creates the platform owner with the password from the environment, never one in the code', function () {
    config(['app.super_admin.email' => 'owner@schoolhub.test', 'app.super_admin.password' => 'from-the-env-123']);

    $this->seed(SuperAdminSeeder::class);

    $owner = User::where('email', 'owner@schoolhub.test')->sole();
    expect(Hash::check('from-the-env-123', $owner->password))->toBeTrue()
        ->and($owner->hasRole('Super Admin'))->toBeTrue();
});

it('makes a random owner password when none is configured, and leaves an existing owner alone', function () {
    config(['app.super_admin.email' => 'owner@schoolhub.test', 'app.super_admin.password' => null]);

    $this->seed(SuperAdminSeeder::class);
    $first = User::where('email', 'owner@schoolhub.test')->sole()->password;

    $this->seed(SuperAdminSeeder::class);

    expect(User::where('email', 'owner@schoolhub.test')->sole()->password)->toBe($first)
        ->and(strlen($first))->toBeGreaterThan(20);
});

it('backs up the database and files, records success and removes old backups', function () {
    config(['database.default' => 'mysql']);
    Process::fake();
    File::ensureDirectoryExists(BackupRun::folder());
    $old = BackupRun::folder().'/schoolhub-db-old.sql.gz';
    File::put($old, 'old');
    touch($old, now()->subDays(20)->getTimestamp());

    $this->artisan('backup:run')->assertSuccessful();

    Process::assertRan(fn ($process): bool => str_contains(implode(' ', (array) $process->command), 'mysqldump')
        && ($process->environment['MYSQL_PWD'] ?? null) !== null);
    expect(File::exists($old))->toBeFalse()
        ->and(Cache::get(BackupRun::LAST_SUCCESS))->not->toBeNull();

    File::deleteDirectory(BackupRun::folder());
});

it('reports a failed backup and does not record it as done', function () {
    config(['database.default' => 'mysql']);
    Process::fake(['*' => Process::result(errorOutput: 'Access denied', exitCode: 2)]);

    $this->artisan('backup:run --no-files')->assertFailed();

    expect(Cache::get(BackupRun::LAST_SUCCESS))->toBeNull();

    File::deleteDirectory(BackupRun::folder());
});

it('flags email, scheduler and backups that are not set up', function () {
    config(['mail.default' => 'log']);

    expect(healthCheck('Email')['status'])->toBe('danger')
        ->and(healthCheck('Scheduler')['status'])->toBe('danger')
        ->and(healthCheck('Backups')['status'])->toBe('danger');

    config(['mail.default' => 'smtp']);
    Cache::forever(SystemHealth::SCHEDULER_HEARTBEAT, now()->getTimestamp());
    Cache::forever(BackupRun::LAST_SUCCESS, now()->subHours(3)->toIso8601String());

    expect(healthCheck('Email')['status'])->toBe('ok')
        ->and(healthCheck('Scheduler')['status'])->toBe('ok')
        ->and(healthCheck('Backups')['status'])->toBe('ok');
});

it('notices a queue worker that is not running', function () {
    config(['queue.default' => 'database']);
    DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subHour()->getTimestamp(), 'created_at' => now()->subHour()->getTimestamp()]);

    expect(healthCheck('Queue worker')['status'])->toBe('danger')
        ->and(healthCheck('Queue worker')['summary'])->toContain('Not running');
});

it('shows System health to the platform owner only', function () {
    $this->actingAs(User::factory()->create()->assignRole('Super Admin'));

    Livewire::test(SystemHealthPage::class)
        ->assertOk()
        ->assertSee(['Email', 'Queue worker', 'Scheduler', 'Backups']);

    $school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);
    SubscriptionManager::startTrial($school);
    $this->actingAs(User::factory()->create(['school_id' => $school->id])->assignRole('School Admin'));

    expect(SystemHealthPage::canAccess())->toBeFalse();
});

it('flags an unsafe production set-up: plain HTTP, an insecure cookie, a log that never rotates', function () {
    app()->detectEnvironment(fn () => 'production');
    config([
        'app.url' => 'http://schoolhub.test',
        'logging.default' => 'stack',
        'logging.channels.stack.channels' => ['single'],
    ]);

    expect(healthCheck('HTTPS')['status'])->toBe('danger')
        ->and(healthCheck('Log files')['status'])->toBe('warning');

    config([
        'app.url' => 'https://schoolhub.test',
        'session.secure' => false,
        'logging.channels.stack.channels' => ['daily'],
    ]);

    expect(healthCheck('HTTPS')['status'])->toBe('warning')
        ->and(healthCheck('Log files')['status'])->toBe('ok');

    config(['session.secure' => true]);

    expect(healthCheck('HTTPS')['status'])->toBe('ok');
});

it('asks platform owners to turn on two-step sign-in', function () {
    $owner = User::factory()->create(['school_id' => null])->assignRole('Super Admin');

    expect(healthCheck('Owner sign-in')['status'])->toBe('warning');

    $owner->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    expect(healthCheck('Owner sign-in')['status'])->toBe('ok')
        ->and($owner->fresh()->getAttributes()['app_authentication_secret'])->not->toBe('JBSWY3DPEHPK3PXP');
});

it('reports the free disk space', function () {
    expect(healthCheck('Disk space')['summary'])->toContain('GB free');
});
