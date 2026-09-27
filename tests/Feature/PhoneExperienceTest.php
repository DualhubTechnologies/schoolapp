<?php

use App\Models\School;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use App\Support\MobileNav;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'St. Mary\'s Primary School Kisubi', 'slug' => 'stm', 'email' => 'stm@example.com', 'school_type' => 'primary']);
    SubscriptionManager::startTrial($this->school);
});

it('names the installed app after the school', function () {
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));

    $this->get(route('filament.app.app.manifest'))
        ->assertOk()
        ->assertJsonPath('name', 'St. Mary\'s Primary School Kisubi')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonCount(2, 'icons');

    $this->get(route('filament.app.app.icon', ['size' => 192]))->assertOk();
});

it('offers SchoolHub\'s own app to visitors', function () {
    $this->get(route('filament.app.app.manifest'))->assertOk()->assertJsonPath('name', 'SchoolHub');
    $this->get(route('filament.app.app.icon', ['size' => 999]))->assertNotFound();
});

it('fills the phone bar with what each person does most', function (string $role, array $expected) {
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole($role));

    expect(collect(MobileNav::items())->pluck('label')->all())->toBe($expected);
})->with([
    'accountant' => ['Accountant', ['Home', 'Receive', 'Balances', 'Payroll']],
    'teacher' => ['Teacher', ['Home', 'Marks', 'Learners']],
    'school admin' => ['School Admin', ['Home', 'Receive', 'Marks', 'Learners']],
]);
