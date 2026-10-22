<?php

use App\Filament\Pages\SchoolSubscription;
use App\Models\MomoPayment;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\Subscriptions\MomoCheckout;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

const MOMO = 'https://sandbox.momodeveloper.mtn.com';

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    config([
        'services.momo.environment' => 'sandbox',
        'services.momo.base_url' => MOMO,
        'services.momo.subscription_key' => 'sub-key',
        'services.momo.api_user' => 'api-user',
        'services.momo.api_key' => 'api-key',
        'services.momo.currency' => 'UGX',
    ]);

    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary', 'status' => 'active']);
    SubscriptionManager::startTrial($this->school);
    $this->plan = Plan::create(['name' => 'Momo Standard', 'slug' => 'momo-standard', 'max_students' => 800, 'max_users' => 25, 'price_per_term' => 600000, 'price_per_year' => 1620000, 'is_trial' => false, 'is_active' => true, 'sort_order' => 1]);

    $this->admin = User::factory()->create(['school_id' => $this->school->id, 'phone' => '0772 123456'])->assignRole('School Admin');
    $this->actingAs($this->admin);

    // MTN: a token, every request to pay accepted, and the status set by fakeMtn().
    Http::fake([
        MOMO.'/collection/token/' => Http::response(['access_token' => 'tok', 'token_type' => 'access_token', 'expires_in' => 3600]),
        MOMO.'/collection/v1_0/requesttopay' => Http::response(null, 202),
        MOMO.'/collection/v1_0/requesttopay/*' => fn () => Http::response($GLOBALS['mtnAnswer']),
    ]);
});

/** What MTN will say about the payment next: $status, for $amount. */
function fakeMtn(string $status, ?string $amount = '600000', string $currency = 'UGX', ?string $reason = null): void
{
    $GLOBALS['mtnAnswer'] = array_filter([
        'amount' => $amount,
        'currency' => $currency,
        'financialTransactionId' => $status === 'SUCCESSFUL' ? '987654321' : null,
        'status' => $status,
        'reason' => $reason,
    ]);
}

it('sends the prompt to the school\'s phone for the plan\'s price', function () {
    fakeMtn('PENDING');

    $payment = app(MomoCheckout::class)->start($this->school, $this->admin, $this->plan, 'term', '0772 123456');

    expect($payment->status)->toBe(MomoPayment::PENDING)
        ->and($payment->phone)->toBe('256772123456')
        ->and($payment->amount)->toBe(600000.0);

    Http::assertSent(fn (Request $request): bool => $request->url() === MOMO.'/collection/v1_0/requesttopay'
        && $request->hasHeader('X-Reference-Id', $payment->reference)
        && $request->hasHeader('X-Target-Environment', 'sandbox')
        && $request->hasHeader('Ocp-Apim-Subscription-Key', 'sub-key')
        && $request->hasHeader('Authorization', 'Bearer tok')
        && $request['amount'] === '600000'
        && $request['currency'] === 'UGX'
        && $request['payer'] === ['partyIdType' => 'MSISDN', 'partyId' => '256772123456']);
});

it('renews the plan once MTN says it was paid, only once however often it is asked', function () {
    fakeMtn('SUCCESSFUL');
    $checkout = app(MomoCheckout::class);
    $payment = $checkout->start($this->school, $this->admin, $this->plan, 'term', '0772123456');

    $checkout->refresh($payment);
    $checkout->refresh($payment->fresh());
    $this->post(route('momo.callback', $payment->reference))->assertOk();

    $payment->refresh();
    expect($payment->status)->toBe(MomoPayment::SUCCESSFUL)
        ->and($payment->financial_transaction_id)->toBe('987654321')
        ->and(SubscriptionPayment::count())->toBe(1)
        ->and(SubscriptionPayment::sole()->reference)->toBe('MTN 987654321')
        ->and((float) SubscriptionPayment::sole()->amount)->toBe(600000.0)
        ->and(SubscriptionManager::current($this->school)->plan_id)->toBe($this->plan->id)
        ->and($payment->subscription->cycle)->toBe('term');
});

it('does not renew when MTN declines, or reports a different amount', function () {
    fakeMtn('FAILED', reason: 'NOT_ENOUGH_FUNDS');
    $checkout = app(MomoCheckout::class);
    $declined = $checkout->refresh($checkout->start($this->school, $this->admin, $this->plan, 'term', '0772123456'));

    expect($declined->status)->toBe(MomoPayment::FAILED)
        ->and($declined->reason)->toBe('There was not enough money on the mobile money account.');

    $this->travel(3)->minutes();
    fakeMtn('SUCCESSFUL', amount: '1000');
    $short = $checkout->refresh($checkout->start($this->school, $this->admin, $this->plan, 'term', '0772123456'));

    expect($short->status)->toBe(MomoPayment::FAILED)
        ->and($short->reason)->toContain('different amount')
        ->and(SubscriptionPayment::count())->toBe(0)
        ->and(Subscription::where('plan_id', $this->plan->id)->exists())->toBeFalse();
});

it('closes a prompt nobody answered, and checks waiting ones on a schedule', function () {
    fakeMtn('PENDING');
    $payment = app(MomoCheckout::class)->start($this->school, $this->admin, $this->plan, 'year', '0772123456');

    $this->travel(5)->minutes();
    $this->artisan('momo:check-pending')->assertSuccessful();
    expect($payment->fresh()->status)->toBe(MomoPayment::PENDING);

    $this->travel(30)->minutes();
    $this->artisan('momo:check-pending')->assertSuccessful();
    expect($payment->fresh()->status)->toBe(MomoPayment::FAILED);
});

it('refuses a number that is not mobile money, and a second prompt straight after the first', function () {
    config(['services.momo.environment' => 'mtnuganda']);
    fakeMtn('PENDING');
    $checkout = app(MomoCheckout::class);

    expect(fn () => $checkout->start($this->school, $this->admin, $this->plan, 'term', '0414 123456'))
        ->toThrow(RuntimeException::class, 'Enter an MTN mobile money number');

    $checkout->start($this->school, $this->admin, $this->plan, 'term', '0772123456');
    expect(fn () => $checkout->start($this->school, $this->admin, $this->plan, 'term', '0772123456'))
        ->toThrow(RuntimeException::class, 'less than two minutes ago');
});

it('lets the school pay on the Subscription page and shows when it has gone through', function () {
    fakeMtn('PENDING');

    $page = Livewire::test(SchoolSubscription::class)
        ->assertSee('Pay now with MTN MoMo')
        ->call('openPay', $this->plan->id)
        ->assertSet('payPhone', '0772 123456')
        ->set('payCycle', 'year')
        ->call('payNow')
        ->assertSee('Check the phone')
        ->assertSee('1,620,000');

    fakeMtn('SUCCESSFUL', amount: '1620000');
    $page->call('checkMomo')->assertSee('Payment received');

    expect(SubscriptionManager::current($this->school)->plan_id)->toBe($this->plan->id);
});

it('hides Pay now until MTN MoMo is set up, and ignores unknown call-backs', function () {
    config(['services.momo.api_key' => null]);

    Livewire::test(SchoolSubscription::class)->assertDontSee('Pay now with MTN MoMo');

    $this->post('/webhooks/momo/'.fake()->uuid())->assertNotFound();
});
