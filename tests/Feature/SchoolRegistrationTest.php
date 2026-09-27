<?php

use App\Filament\Pages\Auth\RegisterSchool;
use App\Models\School;
use App\Models\User;
use App\Support\PasswordStrength;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
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

it('asks production passwords for six characters with upper and lower case letters and a number, no symbol', function () {
    app()->detectEnvironment(fn () => 'production');

    // Without the data-leak lookup, which needs the internet.
    $rule = Password::min(PasswordStrength::MIN_LENGTH)->mixedCase()->letters()->numbers();
    $passes = fn (string $password): bool => Validator::make(['password' => $password], ['password' => $rule])->passes();

    expect($passes('Abc123'))->toBeTrue()
        ->and($passes('abc123'))->toBeFalse()
        ->and($passes('Abcdef'))->toBeFalse()
        ->and(PasswordStrength::MIN_LENGTH)->toBe(6)
        ->and(collect(PasswordStrength::requirements())->pluck('label')->all())
        ->toBe(['At least 6 characters', 'An uppercase letter', 'A lowercase letter', 'A number']);
});
