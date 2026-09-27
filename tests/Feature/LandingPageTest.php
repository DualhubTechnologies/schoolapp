<?php

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
