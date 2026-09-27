<?php

use App\Filament\Admin\Resources\Schools\Pages\ListSchools;
use App\Models\School;
use App\Models\User;
use App\Support\EmailVerificationCode;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('admin');
});

it('lets the platform owner confirm a school administrator\'s email from the Schools list', function () {
    $school = School::create(['name' => 'Nations Pride', 'slug' => 'np', 'email' => 'np@example.com', 'school_type' => 'primary']);
    $admin = User::factory()->create(['school_id' => $school->id, 'email_verified_at' => null])->assignRole('School Admin');
    $admin->forceFill(['email_verification_code' => bcrypt('123456'), 'email_verification_sent_at' => now()])->save();

    $this->actingAs(User::factory()->create()->assignRole('Super Admin'));

    Livewire::test(ListSchools::class)
        ->assertTableActionVisible('confirmAdminEmail', $school)
        ->callTableAction('confirmAdminEmail', $school)
        ->assertHasNoTableActionErrors()
        ->assertNotified('Email confirmed — they can sign in now');

    expect(EmailVerificationCode::isPending($admin->refresh()))->toBeFalse();
});
