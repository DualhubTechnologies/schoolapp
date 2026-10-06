<?php

use App\Filament\App\Resources\TransportRoutes\TransportRouteResource;
use App\Models\School;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use App\Support\Modules;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');
});

function transportSchoolAdmin(string $type): User
{
    $school = School::create(['name' => "Hope {$type}", 'slug' => "hope-{$type}", 'email' => "{$type}@example.com", 'school_type' => $type]);
    SubscriptionManager::startTrial($school);

    return User::factory()->create(['school_id' => $school->id])->assignRole('School Admin');
}

it('gives primary schools the van (Transport)', function () {
    $this->actingAs(transportSchoolAdmin(School::TYPE_PRIMARY));

    expect(Modules::allows('transport'))->toBeTrue()
        ->and(Modules::options())->toHaveKey('transport');

    $this->get(TransportRouteResource::getUrl())->assertOk();
});

it('leaves Transport out for secondary schools, even for a bursar given it before', function () {
    $admin = transportSchoolAdmin(School::TYPE_SECONDARY);
    $this->actingAs($admin);

    expect(Modules::allows('transport'))->toBeFalse()
        ->and(Modules::options())->not->toHaveKey('transport');

    $this->get(TransportRouteResource::getUrl())->assertForbidden();

    $bursar = User::factory()->create(['school_id' => $admin->school_id, 'modules' => ['fees', 'transport']]);

    expect(Modules::forUser($bursar))->toBe(['fees']);
});
