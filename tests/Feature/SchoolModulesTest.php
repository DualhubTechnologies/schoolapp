<?php

use App\Filament\App\Widgets\LeadershipKpis;
use App\Filament\Pages\ReceivePayment;
use App\Filament\Pages\SchoolModules;
use App\Filament\Pages\SchoolPaySettings;
use App\Models\School;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use App\Support\Modules;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Once;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);
    SubscriptionManager::startTrial($this->school);
    $this->admin = User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin');
});

it('lets the school administrator switch modules off and on again', function () {
    $this->actingAs($this->admin);

    $this->get(SchoolModules::getUrl())->assertOk()->assertSee('Library')->assertSee('always on');

    Livewire::test(SchoolModules::class)
        ->assertSet('data.on', array_keys(Modules::switchable($this->school)))
        ->set('data.on', array_values(array_diff(array_keys(Modules::switchable($this->school)), ['fees', 'library'])))
        ->call('save');

    expect($this->school->fresh()->modules_off)->toBe(['fees', 'library']);

    // As on the next page: the school as now saved.
    $this->actingAs($this->admin->fresh());
    Once::flush();

    $this->get(ReceivePayment::getUrl())->assertForbidden();
    expect(Modules::allows('library'))->toBeFalse()
        ->and(SchoolPaySettings::canAccess())->toBeFalse()
        ->and(collect((new LeadershipKpis)->cards())->pluck('label')->all())->not->toContain('Fees collected this term');

    Livewire::test(SchoolModules::class)->set('data.on', array_keys(Modules::switchable($this->school)))->call('save');

    expect($this->school->fresh()->modules_off)->toBe([]);
    $this->actingAs($this->admin->fresh());
    Once::flush();
    $this->get(ReceivePayment::getUrl())->assertOk();
});

it('hides a switched-off module from every user, whatever they were given', function () {
    $this->school->forceFill(['modules_off' => ['fees', 'exams']])->save();
    $bursar = User::factory()->create(['school_id' => $this->school->id])->assignRole('Bursar');

    expect(Modules::forUser($bursar))->not->toContain('fees')->toContain('finance')
        ->and(Modules::forUser($this->admin))->not->toContain('fees')->not->toContain('exams')->not->toContain('exams_all')->toContain('students');

    // Nor can it be handed out on the Users page.
    $this->actingAs($this->admin);
    expect(Modules::options())->not->toHaveKey('fees')->toHaveKey('library');
});

it('keeps the core modules on, and the Modules page for the administrator only', function () {
    $this->school->forceFill(['modules_off' => ['students', 'settings']])->save();

    expect(Modules::forUser($this->admin))->toContain('students')->toContain('settings');

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('Bursar'));
    $this->get(SchoolModules::getUrl())->assertForbidden();
});
