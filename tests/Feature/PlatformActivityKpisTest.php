<?php

use App\Filament\App\Widgets\PlatformActivityKpis;
use App\Models\School;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Facades\Activity;
use Spatie\Activitylog\Models\Activity as ActivityModel;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    config(['session.driver' => 'database']);

    $this->superAdmin = User::factory()->create()->assignRole('Super Admin');
});

function staffOf(string $schoolName): User
{
    $school = School::create([
        'name' => $schoolName,
        'slug' => str($schoolName)->slug()->toString(),
        'email' => str($schoolName)->slug()->append('@example.com')->toString(),
    ]);

    return User::factory()->create(['school_id' => $school->id])->assignRole('School Admin');
}

it('counts who is online and what happened today', function () {
    $kampala = staffOf('Kampala High');
    $gulu = staffOf('Gulu College');

    foreach ([[$kampala, 1], [$gulu, 30]] as [$user, $minutesAgo]) {
        DB::table('sessions')->insert([
            'id' => Str::random(40), 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null,
            'payload' => '', 'last_activity' => now()->subMinutes($minutesAgo)->getTimestamp(),
        ]);
    }

    Activity::causedBy($kampala)->event('login')->log('User logged in');
    Activity::causedBy($kampala)->event('login')->log('User logged in');
    Activity::causedBy($gulu)->event('login')->log('User logged in');
    Activity::withProperties(['email' => 'x@example.com'])->event('login_failed')->log('Failed login attempt');
    // Setting up the schools and users above is itself logged as changes.
    $changesBefore = ActivityModel::whereIn('event', ['created', 'updated', 'deleted'])->count();
    Activity::causedBy($gulu)->event('updated')->log('updated');

    $this->actingAs($this->superAdmin);

    $cards = collect((new PlatformActivityKpis)->cards())->keyBy('label');

    expect($cards['Online now']['value'])->toBe('1')
        ->and($cards['Online now']['sub'])->toStartWith('2 signed in')
        ->and($cards['Signed in today']['value'])->toBe('2')
        ->and($cards['Signed in today']['sub'])->toBe('From 2 schools')
        ->and($cards['Failed sign-ins']['value'])->toBe('1')
        ->and($cards['Failed sign-ins']['tone'])->toBe('rose')
        ->and($cards['Changes today']['value'])->toBe((string) ($changesBefore + 1));
});

it('shows on both Super Admin dashboards', function (string $url) {
    $this->actingAs($this->superAdmin)
        ->get($url)
        ->assertOk()
        ->assertSeeLivewire(PlatformActivityKpis::class)
        ->assertSee(['Online now', 'Signed in today', 'Failed sign-ins', 'Changes today']);
})->with(['/admin', '/dashboard']);

it('is hidden from school users', function () {
    $this->actingAs(staffOf('Kampala High'));

    expect(PlatformActivityKpis::canView())->toBeFalse();
});
