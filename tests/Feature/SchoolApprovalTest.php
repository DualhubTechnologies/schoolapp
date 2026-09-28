<?php

use App\Filament\Admin\Resources\Schools\Pages\ListSchools;
use App\Filament\Pages\Auth\RegisterSchool;
use App\Models\School;
use App\Models\User;
use App\Notifications\SchoolRegistered;
use App\Notifications\SchoolRejected;
use App\Notifications\WelcomeToSchoolHub;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Notification::fake();
    $this->owner = User::factory()->create()->assignRole('Super Admin');
});

/**
 * A school that registered itself a few days ago and is still waiting,
 * with its (email-confirmed) administrator.
 *
 * @return array{0: School, 1: User}
 */
function pendingSchool(): array
{
    test()->travel(-3)->days();

    $school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'head@hope.test', 'school_type' => 'primary', 'status' => 'pending']);
    SubscriptionManager::startTrial($school);
    $admin = User::factory()->create(['school_id' => $school->id])->assignRole('School Admin');

    test()->travelBack();

    return [$school, $admin];
}

it('holds a newly registered school for the platform owner to approve', function () {
    Filament::setCurrentPanel('app');

    Livewire::test(RegisterSchool::class)
        ->fillForm([
            'school_name' => 'Hope Primary School',
            'school_type' => School::TYPE_PRIMARY,
            'city' => 'Wakiso',
            'name' => 'Adrian Mugizi',
            'phone' => '0757490220',
            'email' => 'head@hope.test',
            'password' => 'Abc123',
            'passwordConfirmation' => 'Abc123',
            'terms' => true,
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    $school = School::where('name', 'Hope Primary School')->sole();

    expect($school->status)->toBe('pending')
        ->and(SubscriptionManager::status($school)['state'])->toBe('pending')
        ->and(SubscriptionManager::isLocked($school))->toBeTrue();

    Notification::assertSentTo($this->owner, SchoolRegistered::class);
});

it('lets the platform owner approve a school, starting its trial today and welcoming its administrator', function () {
    [$school, $admin] = pendingSchool();
    Filament::setCurrentPanel('admin');
    $this->actingAs($this->owner);

    Livewire::test(ListSchools::class)
        ->assertTableActionVisible('approve', $school)
        ->callTableAction('approve', $school)
        ->assertHasNoTableActionErrors();

    $school->refresh();
    $trial = SubscriptionManager::current($school);

    expect($school->status)->toBe('active')
        ->and($school->approved_at)->not->toBeNull()
        ->and($school->approved_by)->toBe($this->owner->name)
        ->and($trial->starts_on->isToday())->toBeTrue()
        ->and($trial->ends_on->toDateString())->toBe(today()->addDays(config('subscriptions.trial_days', 30) - 1)->toDateString())
        ->and(SubscriptionManager::status($school)['state'])->toBe('trial');

    Notification::assertSentTo($admin, WelcomeToSchoolHub::class);
});

it('lets the platform owner reject a school with a reason sent to its administrator', function () {
    [$school, $admin] = pendingSchool();
    Filament::setCurrentPanel('admin');
    $this->actingAs($this->owner);

    Livewire::test(ListSchools::class)
        ->callTableAction('reject', $school, ['reason' => 'We could not confirm this school exists.'])
        ->assertHasNoTableActionErrors();

    $school->refresh();

    expect($school->status)->toBe('rejected')
        ->and($school->rejection_reason)->toBe('We could not confirm this school exists.')
        ->and(SubscriptionManager::isLocked($school))->toBeTrue();

    Notification::assertSentTo($admin, SchoolRejected::class);
    Notification::assertNotSentTo($admin, WelcomeToSchoolHub::class);
});

it('offers approval only to schools that are waiting or were turned down', function () {
    $school = School::create(['name' => 'Nations Pride', 'slug' => 'np', 'email' => 'np@example.com', 'school_type' => 'primary', 'status' => 'active']);
    Filament::setCurrentPanel('admin');
    $this->actingAs($this->owner);

    Livewire::test(ListSchools::class)
        ->assertTableActionHidden('approve', $school)
        ->assertTableActionHidden('reject', $school);
});
