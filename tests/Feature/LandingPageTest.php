<?php

use App\Models\Plan;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->withoutVite();
});

it('shows visitors a way to sign in, in the header and under the main buttons', function () {
    $loginUrl = Filament::getPanel('app')->getLoginUrl();

    $this->get('/')
        ->assertOk()
        ->assertSee('class="link-signin"', escape: false)
        ->assertSee('Already registered?')
        ->assertSee('href="'.$loginUrl.'"', escape: false);
});

it('offers signed-in users their dashboard instead', function () {
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole('Super Admin'));

    $this->get('/')
        ->assertOk()
        ->assertSee('Go to dashboard')
        ->assertDontSee('Already registered?');
});

it('advertises plan prices with the landing-page markup, without changing the plans', function () {
    config(['subscriptions.landing_price_markup' => 10]);
    $plan = Plan::create(['name' => 'Tester', 'slug' => 'landing-test', 'price_per_term' => 123000, 'price_per_year' => 321000, 'is_active' => true]);

    // 123,000 + 10% = 135,300 and 321,000 + 10% = 353,100, to the nearest 1,000.
    $this->get('/')
        ->assertOk()
        ->assertSee('135,000')
        ->assertSee('353,000')
        ->assertDontSee('123,000');

    expect((float) $plan->fresh()->price_per_term)->toBe(123000.0);
});
