<?php

use App\Filament\Widgets\SetupChecklist;
use App\Models\School;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $school = School::create(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($school);

    $this->actingAs(User::factory()->create(['school_id' => $school->id])->assignRole('School Admin'));
});

it('shows a new school\'s administrator the next setup step in a card', function () {
    expect(SetupChecklist::canView())->toBeTrue();

    Livewire::test(SetupChecklist::class)
        ->assertSee('Finish setting up')
        ->assertSee('0 of 6 steps done')
        ->assertSee('Complete your school profile')
        ->assertSee('See all steps');
});

it('lets the administrator close the card until they sign in again', function () {
    Livewire::test(SetupChecklist::class)->call('dismiss');

    expect(SetupChecklist::canView())->toBeFalse();

    session()->forget(SetupChecklist::DISMISSED);

    expect(SetupChecklist::canView())->toBeTrue();
});
