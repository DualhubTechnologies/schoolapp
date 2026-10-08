<?php

use App\Filament\App\Widgets\WorkGuide;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($this->school);
});

it('shows a bursar the fee steps and not the marks', function () {
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('Accountant'));

    Livewire::test(WorkGuide::class)
        ->assertSee('Receive a payment')
        ->assertSee('Bill the term')
        ->assertSee('Start this month\'s payroll')
        ->assertDontSee('Enter marks');
});

it('shows a class teacher their register and marks, and not fees', function () {
    $user = User::factory()->create(['school_id' => $this->school->id])->assignRole('Teacher');
    $staff = Staff::create(['school_id' => $this->school->id, 'user_id' => $user->id, 'name' => 'Okello James', 'staff_no' => 'T001', 'position' => 'Teacher', 'employment_date' => '2020-01-06', 'status' => 'active']);
    SchoolClass::create(['school_id' => $this->school->id, 'name' => 'S.1', 'class_teacher_id' => $staff->id]);

    $this->actingAs($user);

    Livewire::test(WorkGuide::class)
        ->assertSee('My class')
        ->assertSee('Take today\'s register')
        ->assertSee('Enter marks for my subjects')
        ->assertSee('Write comments and print report cards')
        ->assertDontSee('Receive a payment');
});

it('can be hidden and brought back', function () {
    $user = User::factory()->create(['school_id' => $this->school->id])->assignRole('Accountant');
    $this->actingAs($user);

    Livewire::test(WorkGuide::class)
        ->call('hideTips')
        ->assertSee('Show the step-by-step guide')
        ->assertDontSee('Receive a payment');

    expect($user->fresh()->tips_hidden_at)->not->toBeNull();

    Livewire::test(WorkGuide::class)
        ->call('showTips')
        ->assertSee('Receive a payment');
});

it('opens the dashboard for every kind of user', function (string $role) {
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole($role))
        ->get(route('filament.app.pages.dashboard'))
        ->assertOk();
})->with(['School Admin', 'Teacher', 'Accountant', 'Staff']);
