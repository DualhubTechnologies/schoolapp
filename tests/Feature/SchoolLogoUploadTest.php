<?php

use App\Filament\Pages\SchoolProfile;
use App\Models\School;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('saves an uploaded logo, shrunk and squared on the server', function () {
    Storage::fake('public');
    $this->seed(RoleSeeder::class);
    $school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);
    SubscriptionManager::startTrial($school);
    $this->actingAs(User::factory()->create(['school_id' => $school->id])->assignRole('School Admin'));
    Filament::setCurrentPanel('app');

    Livewire::test(SchoolProfile::class)
        ->set('data.logo', UploadedFile::fake()->image('logo.png', 1600, 1200))
        ->call('save')
        ->assertHasNoErrors();

    $logo = $school->fresh()->logo;

    expect($logo)->not->toBeNull();

    [$width, $height] = getimagesizefromstring((string) Storage::disk('public')->get($logo));

    expect([$width, $height])->toBe([600, 600]);
});
