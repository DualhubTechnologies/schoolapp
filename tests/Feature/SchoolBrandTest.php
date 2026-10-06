<?php

use App\Filament\App\Resources\Students\StudentResource;
use App\Models\School;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Unity High School', 'slug' => 'unity', 'email' => 'unity@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($this->school);
    $this->admin = User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin');
});

it('shows the school\'s own logo and name in the sidebar', function () {
    $this->school->update(['logo' => 'logos/unity.png']);

    $this->actingAs($this->admin)
        ->get(StudentResource::getUrl())
        ->assertOk()
        ->assertSee('sh-school-brand', false)
        ->assertSee('Unity High School')
        ->assertSee('logos/unity.png', false)
        ->assertDontSee('schoolhub-logo-sidebar.svg', false);
});

it('shows the school\'s initials when it has no logo yet', function () {
    $this->actingAs($this->admin)
        ->get(StudentResource::getUrl())
        ->assertOk()
        ->assertSee('sh-school-brand-initials', false)
        ->assertSee('>UH<', false);
});
