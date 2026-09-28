<?php

use App\Filament\Pages\Auth\RegisterSchool;
use App\Filament\Pages\Auth\VerifyEmail;
use App\Models\School;
use App\Models\User;
use App\Support\PasswordStrength;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Notifications\ChannelManager;
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

it('sends a newly registered school to confirm its email, signed out', function () {
    Livewire::test(RegisterSchool::class)
        ->fillForm(registrationData())
        ->call('register')
        ->assertHasNoFormErrors()
        ->assertRedirect(VerifyEmail::url());

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
        ->assertRedirect(VerifyEmail::url());

    expect(User::where('email', 'head@hope.test')->exists())->toBeTrue();
});

it('does not use a real school as the example name', function () {
    $this->withoutVite();

    $this->get(Filament::getPanel('app')->getRegistrationUrl())
        ->assertOk()
        ->assertSee("Your school's full name")
        ->assertDontSee('Kisubi');
});

it('still creates the account and opens the code page when the mail server is down', function () {
    User::factory()->create()->assignRole('Super Admin');

    // Real sending (not the fake) to a mail server that refuses the connection.
    Notification::swap(new ChannelManager(app()));
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 1,
        'mail.mailers.smtp.timeout' => 1,
    ]);

    Livewire::test(RegisterSchool::class)
        ->fillForm(registrationData())
        ->call('register')
        ->assertHasNoFormErrors()
        ->assertNotified('We could not send the email just now')
        ->assertRedirect(VerifyEmail::url());

    expect(User::where('email', 'head@hope.test')->sole()->school->status)->toBe('pending');
});

it('uses a short mail timeout so a dead mail server fails fast', function () {
    expect(config('mail.mailers.smtp.timeout'))->toBe(10);
});
