<?php

use App\Filament\Admin\Resources\Schools\Pages\EditSchool;
use App\Filament\Pages\SchoolProfile;
use App\Models\School;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->school = School::create([
        'name' => 'Kasokoso Primary School', 'slug' => 'kasokoso', 'unique_code' => 'SH84914',
        'email' => 'head@kasokoso.test', 'school_type' => 'primary', 'status' => 'pending',
        'contact_person' => 'Andrew Koola', 'phone' => '0700555326',
    ]);
    SubscriptionManager::startTrial($this->school);
});

it('shows the platform owner the school in a few plain sections', function () {
    $this->actingAs(User::factory()->create()->assignRole('Super Admin'));
    Filament::setCurrentPanel('admin');

    Livewire::test(EditSchool::class, ['record' => $this->school->getRouteKey()])
        ->assertOk()
        ->assertSee(['School profile', 'SchoolHub account', 'Payments & settings', 'Branding'])
        ->assertDontSee('e.g. Ora et Labora')
        ->assertFormFieldExists('contact_person')
        ->assertFormSet(['contact_person' => 'Andrew Koola', 'status' => 'pending', 'unique_code' => 'SH84914'])
        ->assertActionVisible('approve')
        ->assertActionVisible('renew')
        ->assertActionExists('extend')
        ->fillForm(['contact_title' => 'Head teacher', 'motto' => 'Knowledge is power'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->school->fresh())
        ->contact_title->toBe('Head teacher')
        ->motto->toBe('Knowledge is power')
        ->unique_code->toBe('SH84914');
});

it('shows a school its own profile without the platform owner\'s account section', function () {
    $this->school->update(['status' => 'active']);
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
    Filament::setCurrentPanel('app');

    Livewire::test(SchoolProfile::class)
        ->assertOk()
        ->assertSee(['School profile', 'Payments & settings'])
        ->assertDontSee('SchoolHub account')
        ->assertFormSet(['unique_code' => 'SH84914'])
        ->fillForm(['contact_title' => 'Director'])
        ->call('save')
        ->assertHasNoErrors();

    expect($this->school->fresh())
        ->contact_title->toBe('Director')
        ->status->toBe('active')
        ->unique_code->toBe('SH84914');
});
