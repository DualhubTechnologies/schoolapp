<?php

use App\Filament\Admin\Resources\ActivationCodes\Pages\ManageActivationCodes;
use App\Models\Plan;
use App\Models\School;
use App\Models\SubscriptionActivationCode;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-10-22 09:00:00');
    $this->seed(RoleSeeder::class);
    $this->withoutVite();

    $this->plan = Plan::create(['name' => 'Gift Standard', 'slug' => 'gift-standard', 'max_students' => 800, 'max_users' => 25, 'price_per_term' => 240000, 'price_per_year' => 648000, 'is_trial' => false, 'is_active' => true, 'sort_order' => 1]);
    $this->owner = User::factory()->create()->assignRole('Super Admin');
});

it('gives a new school a full term free', function () {
    $school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);
    $trial = SubscriptionManager::startTrial($school);

    expect($trial->ends_on->toDateString())->toBe('2027-02-21')
        ->and(SubscriptionManager::trialLabel())->toBe('a full term (4 months)');
});

it('makes gift vouchers in the admin panel, free and nothing recorded as paid', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs($this->owner);

    Livewire::test(ManageActivationCodes::class)
        ->callAction('gift', data: ['plan_id' => $this->plan->id, 'cycle' => 'term', 'count' => 6, 'valid_days' => 120, 'notes' => 'Wakiso head teachers\' meeting'])
        ->assertHasNoActionErrors();

    $codes = SubscriptionActivationCode::where('is_gift', true)->get();
    expect($codes)->toHaveCount(6)
        ->and($codes->every(fn ($code) => (float) $code->amount === 0.0 && (float) $code->payment_amount === 0.0 && $code->school_id === null))->toBeTrue()
        ->and($codes->first()->expires_at->toDateString())->toBe('2027-02-19')
        ->and($codes->first()->notes)->toBe('Wakiso head teachers\' meeting');
});

it('starts a gift voucher after the school\'s free term, without cutting it short', function () {
    $school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary', 'status' => 'active']);
    $trial = SubscriptionManager::startTrial($school);
    [$voucher] = SubscriptionManager::issueGiftVouchers($this->plan, 'term', 1, 120);

    $this->travel(10)->days();
    $term = SubscriptionManager::redeemActivationCode($school, $voucher->code);

    expect($trial->fresh()->ends_on->toDateString())->toBe('2027-02-21')
        ->and($term->starts_on->toDateString())->toBe('2027-02-22')
        ->and($term->plan_id)->toBe($this->plan->id)
        ->and(SubscriptionPayment::count())->toBe(0)
        ->and($voucher->fresh()->school_id)->toBe($school->id);

    // A code someone paid for still starts at once, as before.
    $paid = SubscriptionManager::issueActivationCode(null, $this->plan, 'term', 240000, ['amount' => 240000, 'method' => 'mobile_money']);
    $other = School::create(['name' => 'Kito Primary', 'slug' => 'kito', 'email' => 'kito@example.com', 'school_type' => 'primary', 'status' => 'active']);
    SubscriptionManager::startTrial($other);
    expect(SubscriptionManager::redeemActivationCode($other, $paid->code)->starts_on->toDateString())->toBe(today()->toDateString());
});

it('prints unused vouchers four to a page, for the platform owner only', function () {
    $codes = SubscriptionManager::issueGiftVouchers($this->plan, 'term', 5, 120);
    $codes[4]->update(['used_at' => now()]);
    $url = route('filament.admin.gift-vouchers', ['codes' => $codes->pluck('id')->implode(',')]);

    $this->actingAs($this->owner)->get($url)->assertOk()
        ->assertSee('GIFT VOUCHER')
        ->assertSee('1 term free')
        ->assertSee('Gift Standard plan')
        ->assertSee($codes[0]->code)
        ->assertSee($codes[3]->code)
        ->assertDontSee($codes[4]->code)
        ->assertSee('19 Feb 2027')
        ->assertSee('4 vouchers, 4 to a page');

    $school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);
    $this->actingAs(User::factory()->create(['school_id' => $school->id])->assignRole('School Admin'))->get($url)->assertForbidden();
});
