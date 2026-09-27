<?php

use App\Filament\Admin\Resources\OnlineUsers\OnlineUserResource;
use App\Filament\Admin\Resources\OnlineUsers\Pages\ListOnlineUsers;
use App\Models\School;
use App\Models\User;
use App\Models\UserSession;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    config(['session.driver' => 'database']);

    $this->superAdmin = User::factory()->create()->assignRole('Super Admin');
});

function userAtSchool(string $schoolName): User
{
    $school = School::create([
        'name' => $schoolName,
        'slug' => str($schoolName)->slug()->toString(),
        'email' => str($schoolName)->slug()->append('@example.com')->toString(),
    ]);

    return User::factory()->create(['school_id' => $school->id])->assignRole('School Admin');
}

function sessionFor(?User $user, int $minutesAgo, string $agent = 'Mozilla/5.0 (Linux; Android 14) Chrome/130.0 Mobile Safari/537.36'): void
{
    DB::table('sessions')->insert([
        'id' => Str::random(40),
        'user_id' => $user?->id,
        'ip_address' => '41.210.1.1',
        'user_agent' => $agent,
        'payload' => '',
        'last_activity' => now()->subMinutes($minutesAgo)->getTimestamp(),
    ]);
}

it('shows who is signed in, with their school, status and device', function () {
    $active = userAtSchool('Kampala High');
    $idle = userAtSchool('Gulu College');
    $expired = userAtSchool('Mbarara SS');

    sessionFor($active, 1);
    sessionFor($idle, 30, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0 Safari/537.36');
    sessionFor($expired, 60 * 24);
    sessionFor(null, 1);

    Filament::setCurrentPanel('admin');
    $this->actingAs($this->superAdmin);

    Livewire::test(ListOnlineUsers::class)
        ->assertOk()
        ->assertCanSeeTableRecords(UserSession::whereIn('user_id', [$active->id, $idle->id])->get())
        ->assertSee([$active->name, 'Kampala High', 'Active now', 'Chrome on Android'])
        ->assertSee([$idle->name, 'Gulu College', 'Idle', 'Chrome on Windows'])
        ->assertDontSee($expired->name);
});

it('counts people active now on the sidebar badge', function () {
    $user = userAtSchool('Kampala High');
    sessionFor($user, 1);
    sessionFor($user, 2);
    sessionFor(userAtSchool('Gulu College'), 30);

    $this->actingAs($this->superAdmin);

    expect(OnlineUserResource::getNavigationBadge())->toBe('1');
});

it('filters to people active now', function () {
    $active = userAtSchool('Kampala High');
    $idle = userAtSchool('Gulu College');
    sessionFor($active, 1);
    sessionFor($idle, 30);

    Filament::setCurrentPanel('admin');
    $this->actingAs($this->superAdmin);

    Livewire::test(ListOnlineUsers::class)
        ->filterTable('active_now', true)
        ->assertSee($active->name)
        ->assertDontSee($idle->name);
});

it('is in the Super Admin sidebar on both panels', function (string $url) {
    $this->actingAs($this->superAdmin)
        ->get($url)
        ->assertOk()
        ->assertSee('Online users');
})->with(['/admin', '/dashboard']);

it('is closed to school users', function () {
    $this->actingAs(userAtSchool('Kampala High'))
        ->get('/admin/online-users')
        ->assertForbidden();
});
