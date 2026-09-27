<?php

use App\Filament\Pages\Auth\RegisterSchool;
use App\Models\School;
use App\Models\User;
use App\Support\PasswordStrength;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Notification::fake();
    Filament::setCurrentPanel('app');
});

function registrationData(array $overrides = []): array
{
    return array_merge([
        'school_name' => 'Hope Primary School',
        'school_type' => School::TYPE_PRIMARY,
        'city' => 'Wakiso',
        'name' => 'Adrian Mugizi',
        'phone' => '0757490220',
        'email' => 'head@hope.test',
        'password' => 'Abc123',
        'passwordConfirmation' => 'Abc123',
        'terms' => true,
    ], $overrides);
}

it('sends a newly registered school to the sign-in page, signed out', function () {
    Livewire::test(RegisterSchool::class)
        ->fillForm(registrationData())
        ->call('register')
        ->assertHasNoFormErrors()
        ->assertRedirect(Filament::getLoginUrl());

    $user = User::where('email', 'head@hope.test')->sole();

    expect(auth()->check())->toBeFalse()
        ->and($user->hasRole('School Admin'))->toBeTrue()
        ->and($user->school->name)->toBe('Hope Primary School');
});

it('accepts a six-character password and rejects a shorter one', function () {
    Livewire::test(RegisterSchool::class)
        ->fillForm(registrationData(['password' => 'Ab12c', 'passwordConfirmation' => 'Ab12c']))
        ->call('register')
        ->assertHasFormErrors(['password']);

    expect(User::where('email', 'head@hope.test')->exists())->toBeFalse();
});

it('only enforces six characters in production; letters and numbers are advice', function () {
    app()->detectEnvironment(fn () => 'production');

    $passes = fn (string $password): bool => Validator::make(['password' => $password], ['password' => PasswordStrength::rule()])->passes();

    expect($passes('abc123'))->toBeTrue()
        ->and($passes('abcdef'))->toBeTrue()
        ->and($passes('123456'))->toBeTrue()
        ->and($passes('abc12'))->toBeFalse()
        ->and(PasswordStrength::MIN_LENGTH)->toBe(6)
        ->and(collect(PasswordStrength::requirements())->pluck('label')->all())
        ->toBe(['At least 6 characters', 'A letter', 'A number']);
});

it('lets someone who insists register with a weak six-character password', function () {
    Livewire::test(RegisterSchool::class)
        ->fillForm(registrationData(['password' => '123456', 'passwordConfirmation' => '123456']))
        ->call('register')
        ->assertHasNoFormErrors()
        ->assertRedirect(Filament::getLoginUrl());

    expect(User::where('email', 'head@hope.test')->exists())->toBeTrue();
});

it('does not use a real school as the example name', function () {
    $this->withoutVite();

    $this->get(Filament::getPanel('app')->getRegistrationUrl())
        ->assertOk()
        ->assertSee("Your school's full name")
        ->assertDontSee('Kisubi');
});
