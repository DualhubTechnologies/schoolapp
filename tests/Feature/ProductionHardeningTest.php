<?php

use App\Filament\Auth\ConfirmedAppAuthentication;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Auth\Pages\EditProfile;
use Filament\Facades\Filament;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('sends browser protections with every page', function () {
    $this->get('/')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy');
});

it('asks for the authenticator code after the password once two-step sign-in is on', function () {
    Filament::setCurrentPanel('admin');
    $secret = app(Google2FA::class)->generateSecretKey();
    $owner = User::factory()->create(['school_id' => null, 'password' => 'Str0ng!Passw0rd'])->assignRole('Super Admin');
    $owner->saveAppAuthenticationSecret($secret);

    $login = Livewire::test(Filament::getCurrentPanel()->getLoginRouteAction())
        ->fillForm(['email' => $owner->email, 'password' => 'Str0ng!Passw0rd'])
        ->call('authenticate')
        ->assertSet('userUndertakingMultiFactorAuthentication', fn ($value) => filled($value));

    $this->assertGuest();

    $login->fillForm(['app.code' => app(Google2FA::class)->getCurrentOtp($secret)], 'multiFactorChallengeForm')
        ->call('authenticate');

    $this->assertAuthenticatedAs($owner);
});

it('gives school users a profile page where two-step sign-in can be set up', function () {
    Filament::setCurrentPanel('app');
    $user = User::factory()->create()->assignRole('School Admin');

    $this->actingAs($user);

    Livewire::test(EditProfile::class)->assertOk()->assertSee('Authenticator app');
});

it('links to the profile, where two-step sign-in is set up, from the user menu', function () {
    Filament::setCurrentPanel('admin');
    $owner = User::factory()->create(['school_id' => null])->assignRole('Super Admin');

    $this->actingAs($owner)
        ->get(Filament::getPanel('admin')->getUrl())
        ->assertOk()
        ->assertSee('My profile &amp; security', false)
        ->assertSee(Filament::getPanel('admin')->getProfileUrl(), false);
});

it('asks for the password before an authenticator app can be linked', function () {
    Filament::setCurrentPanel('admin');
    $owner = User::factory()->create(['school_id' => null])->assignRole('Super Admin');
    $this->actingAs($owner);
    $setUpLabel = __('filament-panels::auth/multi-factor/app/actions/set-up.label');

    Livewire::test(EditProfile::class)
        ->assertSee('Confirm your password to set up')
        ->assertDontSee($setUpLabel);

    session()->put(ConfirmedAppAuthentication::SESSION_KEY, time());

    Livewire::test(EditProfile::class)
        ->assertDontSee('Confirm your password to set up')
        ->assertSee($setUpLabel);

    // The confirmation runs out after ten minutes.
    session()->put(ConfirmedAppAuthentication::SESSION_KEY, time() - 601);

    expect(ConfirmedAppAuthentication::passwordRecentlyConfirmed())->toBeFalse();
    Livewire::test(EditProfile::class)->assertDontSee($setUpLabel);
});
