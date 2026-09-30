<?php

use App\Filament\App\Resources\Students\Pages\ListStudents;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('lists only the school\'s own classes in the Students class filter', function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $secondary = School::create(['name' => 'Light Secondary', 'slug' => 'light', 'email' => 'light@example.com', 'school_type' => 'secondary']);
    $primary = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);
    SubscriptionManager::startTrial($secondary);

    SchoolClass::create(['school_id' => $secondary->id, 'name' => 'S.1']);
    SchoolClass::create(['school_id' => $primary->id, 'name' => 'Baby Class']);

    $this->actingAs(User::factory()->create(['school_id' => $secondary->id])->assignRole('School Admin'));

    $options = Livewire::test(ListStudents::class)->instance()->getTable()->getFilter('school_class_id')->getOptions();

    expect(array_values($options))->toBe(['S.1']);
});
