<?php

use App\Filament\App\Resources\Users\Pages\ListUsers;
use App\Http\Middleware\RecordLastSeen;
use App\Models\School;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();

    $this->school = School::create([
        'name' => 'Kampala High',
        'slug' => 'kampala-high',
        'email' => 'kampala-high@example.com',
    ]);

    $this->admin = User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin');
});

it('records when a signed-in user was last seen', function () {
    $this->freezeSecond();

    $this->actingAs($this->admin)->get(filament()->getPanel('app')->getUrl());

    expect($this->admin->fresh()->last_seen_at?->equalTo(now()))->toBeTrue();
});

it('writes last seen at most once every few minutes, without touching updated_at', function () {
    $this->freezeSecond();
    $recently = now()->subMinutes(RecordLastSeen::EVERY_MINUTES - 1);
    $updatedAt = now()->subDay();

    $this->admin->forceFill(['last_seen_at' => $recently, 'updated_at' => $updatedAt])->saveQuietly();

    $this->actingAs($this->admin)->get(filament()->getPanel('app')->getUrl());

    expect($this->admin->fresh()->last_seen_at->equalTo($recently))->toBeTrue();

    $this->travel(2)->minutes();
    $this->actingAs($this->admin->fresh())->get(filament()->getPanel('app')->getUrl());

    $admin = $this->admin->fresh();
    expect($admin->last_seen_at->equalTo(now()))->toBeTrue()
        ->and($admin->updated_at->equalTo($updatedAt))->toBeTrue();
});

it('shows last seen on the users list', function () {
    $this->freezeSecond();
    $this->admin->forceFill(['last_seen_at' => now()])->saveQuietly();

    $bursar = User::factory()->create(['school_id' => $this->school->id]);
    $bursar->forceFill(['last_seen_at' => now()->subHours(3)])->saveQuietly();
    $teacher = User::factory()->create(['school_id' => $this->school->id]);

    Filament::setCurrentPanel('app');
    $this->actingAs($this->admin);

    Livewire::test(ListUsers::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$bursar, $teacher])
        ->assertSee(['Last seen', '3 hours ago', 'Never']);
});
