<?php

use App\Filament\Widgets\SchoolOnboardingFunnel;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Dashboard\OnboardingFunnel;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Activitylog\Facades\Activity;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('shows how far new schools got and who stopped where', function () {
    $active = School::create(['name' => 'Busy Primary', 'slug' => 'busy', 'email' => 'busy@example.com', 'school_type' => 'primary']);
    $admin = User::factory()->create(['school_id' => $active->id])->assignRole('School Admin');
    Activity::causedBy($admin)->event('login')->log('User logged in');
    $class = SchoolClass::create(['school_id' => $active->id, 'name' => 'P.1']);
    Student::create(['school_id' => $active->id, 'school_class_id' => $class->id, 'name' => 'Aisha', 'admission_no' => 'A1', 'status' => 'active']);

    $quiet = School::create(['name' => 'Quiet College', 'slug' => 'quiet', 'email' => 'quiet@example.com', 'school_type' => 'secondary']);
    User::factory()->create(['school_id' => $quiet->id, 'email_verified_at' => null, 'email_verification_code' => 'hash'])->assignRole('School Admin');

    // Schools from before the setup offer are not "new".
    School::create(['name' => 'Old School', 'slug' => 'old', 'email' => 'old@example.com', 'setup_choice' => 'existing']);

    $funnel = app(OnboardingFunnel::class)->build();
    $counts = collect($funnel['steps'])->pluck('count', 'key');

    expect($funnel['total'])->toBe(2)
        ->and($counts->all())->toBe(['registered' => 2, 'confirmed' => 1, 'signed_in' => 1, 'classes' => 1, 'learners' => 1, 'fees' => 0, 'paid' => 0])
        ->and(collect($funnel['stuck'])->mapWithKeys(fn ($row) => [$row['school']->name => $row['step']])->sortKeys()->all())
        ->toBe(['Busy Primary' => 'Set fees', 'Quiet College' => 'Confirmed email']);
});

it('shows the funnel on the platform dashboard', function () {
    Filament::setCurrentPanel('admin');
    School::create(['name' => 'Quiet College', 'slug' => 'quiet', 'email' => 'quiet@example.com', 'phone' => '0772123456', 'school_type' => 'secondary']);
    $this->actingAs(User::factory()->create()->assignRole('Super Admin'));

    Livewire::test(SchoolOnboardingFunnel::class)
        ->assertSee('Where new schools get stuck')
        ->assertSee('Quiet College')
        ->assertSee('wa.me/256772123456', false);
});
