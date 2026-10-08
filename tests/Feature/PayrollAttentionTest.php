<?php

use App\Filament\App\Resources\PayrollPeriods\PayrollPeriodResource;
use App\Models\PayrollPeriod;
use App\Models\School;
use App\Models\User;
use App\Services\AttentionItems;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($this->school);
    $this->admin = User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin');
});

it('links a draft or unpaid payroll in the bell to the payroll, and every page still opens', function (string $status) {
    $period = PayrollPeriod::create(['school_id' => $this->school->id, 'month' => 10, 'year' => 2026, 'status' => $status]);

    $item = collect(AttentionItems::for($this->admin))->firstWhere('key', "payroll-{$period->id}");

    expect($item['url'] ?? null)->toBe(PayrollPeriodResource::getUrl('view', ['record' => $period], panel: 'app'));

    // The bell is on every page: a payroll waiting for approval must not break them.
    $this->actingAs($this->admin)->get(route('filament.app.pages.dashboard'))->assertOk();
    $this->get(PayrollPeriodResource::getUrl(panel: 'app'))->assertOk();
})->with(['draft', 'approved']);
