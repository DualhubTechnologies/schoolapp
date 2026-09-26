<?php

use App\Filament\App\Widgets\RecentActivity;
use App\Models\School;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Activitylog\Facades\Activity;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('app');

    $this->superAdmin = User::factory()->create()->assignRole('Super Admin');
});

function schoolAdminAt(string $schoolName): User
{
    $school = School::create([
        'name' => $schoolName,
        'slug' => str($schoolName)->slug()->toString(),
        'email' => str($schoolName)->slug()->append('@example.com')->toString(),
    ]);

    return User::factory()->create(['school_id' => $school->id])->assignRole('School Admin');
}

it('shows activity from every school to the Super Admin', function () {
    $kampala = schoolAdminAt('Kampala High');
    $gulu = schoolAdminAt('Gulu College');

    Activity::causedBy($kampala)->event('login')->log('User logged in');
    Activity::causedBy($gulu)->event('updated')->log('updated');
    Activity::withProperties(['email' => 'intruder@example.com'])->event('login_failed')->log('Failed login attempt');

    $this->actingAs($this->superAdmin);

    Livewire::test(RecentActivity::class)
        ->assertOk()
        ->assertSee(['Kampala High', 'Gulu College', $kampala->name, $gulu->name])
        ->assertSee(['Signed in', 'Updated', 'Failed sign-in', 'intruder@example.com']);
});

it('is hidden from school users', function () {
    $this->actingAs(schoolAdminAt('Kampala High'));

    expect(RecentActivity::canView())->toBeFalse();
});

it('appears on the Super Admin dashboard', function () {
    $this->withoutVite();

    $this->actingAs($this->superAdmin)
        ->get('/dashboard')
        ->assertOk()
        ->assertSeeLivewire(RecentActivity::class);
});

it('appears on the admin panel dashboard', function () {
    $this->withoutVite();

    $this->actingAs($this->superAdmin)
        ->get('/admin')
        ->assertOk()
        ->assertSeeLivewire(RecentActivity::class);
});
