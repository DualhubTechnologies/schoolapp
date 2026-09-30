<?php

use App\Filament\App\Resources\Students\Pages\ListStudents;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use App\Support\OwnSchool;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->secondary = School::create(['name' => 'Light Secondary', 'slug' => 'light', 'email' => 'light@example.com', 'school_type' => 'secondary']);
    $primary = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);
    SubscriptionManager::startTrial($this->secondary);

    SchoolClass::create(['school_id' => $this->secondary->id, 'name' => 'S.1']);
    SchoolClass::create(['school_id' => $primary->id, 'name' => 'Baby Class']);

    $this->actingAs(User::factory()->create(['school_id' => $this->secondary->id])->assignRole('School Admin'));
});

it('limits a query to the signed-in user\'s school', function () {
    expect(OwnSchool::scope(SchoolClass::query())->pluck('name')->all())->toBe(['S.1']);
});

it('lists only the school\'s own classes in the Students class filter', function () {
    // The filter's options are preloaded into the page.
    Livewire::test(ListStudents::class)
        ->assertSee('S.1')
        ->assertDontSee('Baby Class');
});
