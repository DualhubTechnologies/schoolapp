<?php

use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Filament\Pages\Auth\ResetPassword;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('app');
    $this->withoutVite();
});

it('shows the sign-in page in the SchoolHub design, with the parents note and registration', function () {
    $this->get(Filament::getLoginUrl())
        ->assertOk()
        ->assertSee('Welcome back')
        ->assertSee('class="sha-card', false)
        ->assertSee('Parents:', false)
        ->assertSee('sha-note sha-note-parents', false) // left out of the phone layout
        ->assertSee('Register your school');
});

it('shows the forgot-password page in the same design and sends the link', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->get(Filament::getRequestPasswordResetUrl())
        ->assertOk()
        ->assertSee('Forgot your password?')
        ->assertSee('class="sha-card', false)
        ->assertSee('Back to sign in');

    Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => $user->email])
        ->call('request')
        ->assertHasNoFormErrors();

    Notification::assertSentTo($user, \Filament\Auth\Notifications\ResetPassword::class);
});

it('lets a user choose a new password from the emailed link', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    Livewire::withQueryParams(['email' => $user->email, 'token' => $token])
        ->test(ResetPassword::class)
        ->assertSee('Choose a new password')
        ->assertFormSet(['email' => $user->email])
        ->fillForm(['password' => 'New-pass-2026', 'passwordConfirmation' => 'New-pass-2026'])
        ->call('resetPassword')
        ->assertHasNoFormErrors();

    expect(password_verify('New-pass-2026', $user->fresh()->password))->toBeTrue();
});

it('binds every sign-in page to its form, not to a stylesheet before it', function (string $url) {
    // Livewire marks the page's first element; if a <link> or <style> came
    // first, the form outside it would stop working.
    expect($this->get($url)->getContent())->toMatch('/<div [^>]*wire:id="[^"]+"[^>]*class="sha"/');
})->with([
    'sign in' => fn () => Filament::getLoginUrl(),
    'forgot password' => fn () => Filament::getRequestPasswordResetUrl(),
]);
