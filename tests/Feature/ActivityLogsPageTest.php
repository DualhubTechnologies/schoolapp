<?php

use App\Filament\Admin\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Models\School;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Activitylog\Facades\Activity;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();

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

it('lists activity from every school for the Super Admin', function () {
    $kampala = schoolAdminAt('Kampala High');
    $gulu = schoolAdminAt('Gulu College');

    Activity::causedBy($kampala)->event('login')->log('User logged in');
    Activity::causedBy($gulu)->event('updated')->log('updated');
    Activity::withProperties(['email' => 'intruder@example.com'])->event('login_failed')->log('Failed login attempt');

    Filament::setCurrentPanel('admin');
    $this->actingAs($this->superAdmin);

    Livewire::test(ListActivityLogs::class)
        ->assertOk()
        ->assertSee(['Kampala High', 'Gulu College', $kampala->name, $gulu->name])
        ->assertSee(['Signed in', 'Updated', 'Failed sign-in', 'intruder@example.com']);
});

it('filters activity by school', function () {
    $kampala = schoolAdminAt('Kampala High');
    $gulu = schoolAdminAt('Gulu College');

    Activity::causedBy($kampala)->event('login')->log('User logged in');
    Activity::causedBy($gulu)->event('login')->log('User logged in');

    Filament::setCurrentPanel('admin');
    $this->actingAs($this->superAdmin);

    Livewire::test(ListActivityLogs::class)
        ->filterTable('school', $kampala->school_id)
        ->assertSee($kampala->name)
        ->assertDontSee($gulu->name);
});

it('is in the Super Admin sidebar on both panels', function (string $url) {
    $this->actingAs($this->superAdmin)
        ->get($url)
        ->assertOk()
        ->assertSee('Activity logs');
})->with(['/admin', '/dashboard']);

it('is closed to school users', function () {
    $this->actingAs(schoolAdminAt('Kampala High'))
        ->get('/admin/activity-logs')
        ->assertForbidden();
});
