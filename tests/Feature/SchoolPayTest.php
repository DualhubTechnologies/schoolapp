<?php

use App\Filament\App\Resources\SchoolPayTransactions\Pages\ListSchoolPayTransactions;
use App\Filament\Pages\SchoolPaySettings;
use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolPayTransaction;
use App\Models\Student;
use App\Models\StudentPayment;
use App\Models\Term;
use App\Models\User;
use App\Services\SchoolPay\SchoolPayPayments;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();

    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);
    SubscriptionManager::startTrial($this->school);
    $this->school->update(['schoolpay_enabled' => true, 'schoolpay_school_code' => '809', 'schoolpay_api_password' => 'sp-secret']);

    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    $this->term = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true]);

    $this->aisha = Student::create(['school_id' => $this->school->id, 'name' => 'Aisha Nakato', 'admission_no' => 'ADM-001', 'schoolpay_code' => '1000000001', 'status' => 'active']);
    $this->okello = Student::create(['school_id' => $this->school->id, 'name' => 'Okello Brian', 'admission_no' => 'HIS-252', 'status' => 'active']);
});

/**
 * @return array<string, mixed>
 */
function schoolPayPayment(string $receipt, array $overrides = []): array
{
    return array_merge([
        'amount' => '350000',
        'paymentDateAndTime' => '2026-10-05 12:45:42',
        'schoolpayReceiptNumber' => $receipt,
        'settlementBankCode' => 'CENTENARY',
        'sourceChannelTransDetail' => 'PHIONAH NABALAMBA',
        'sourceChannelTransactionId' => '90269163351',
        'sourcePaymentChannel' => 'Airtel Money',
        'studentName' => 'Aisha Nakato',
        'studentPaymentCode' => '1000000001',
        'studentRegistrationNumber' => '',
        'transactionCompletionStatus' => 'Completed',
    ], $overrides);
}

function postSchoolPayWebhook(School $school, array $payment, string $type = 'SCHOOL_FEES', ?string $signature = null)
{
    return test()->postJson(route('schoolpay.webhook', ['token' => $school->fresh()->schoolpay_webhook_token]), [
        'signature' => $signature ?? hash('sha256', 'sp-secret'.$payment['schoolpayReceiptNumber']),
        'type' => $type,
        'payment' => $payment,
    ]);
}

it('keeps the SchoolPay API password encrypted and out of the page', function () {
    expect(DB::table('schools')->where('id', $this->school->id)->value('schoolpay_api_password'))->not->toBe('sp-secret')
        ->and($this->school->fresh()->schoolpay_api_password)->toBe('sp-secret')
        ->and($this->school->fresh()->toArray())->not->toHaveKeys(['schoolpay_api_password', 'schoolpay_webhook_token'])
        ->and($this->school->fresh()->schoolPayWebhookUrl())->toContain('/webhooks/schoolpay/');
});

it('records a signed web hook payment on the learner with that SchoolPay code', function () {
    postSchoolPayWebhook($this->school, schoolPayPayment('18843014'))->assertOk();

    $payment = StudentPayment::sole();

    expect($payment)
        ->student_id->toBe($this->aisha->id)
        ->term_id->toBe($this->term->id)
        ->method->toBe('schoolpay')
        ->reference->toBe('18843014')
        ->paid_by->toBe('PHIONAH NABALAMBA')
        ->recorded_by->toBe('SchoolPay')
        ->and((float) $payment->amount)->toBe(350000.0)
        ->and($payment->paid_on->toDateString())->toBe('2026-10-05')
        ->and(SchoolPayTransaction::sole())
        ->status->toBe('recorded')
        ->student_payment_id->toBe($payment->id);
});

it('records each SchoolPay receipt once, however often it is sent', function () {
    postSchoolPayWebhook($this->school, schoolPayPayment('18843014'))->assertOk();
    postSchoolPayWebhook($this->school, schoolPayPayment('18843014'))->assertOk();

    expect(StudentPayment::count())->toBe(1)
        ->and(SchoolPayTransaction::count())->toBe(1);
});

it('refuses a web hook with a wrong signature or an unknown address', function () {
    postSchoolPayWebhook($this->school, schoolPayPayment('18843014'), signature: hash('sha256', 'guess'.'18843014'))->assertForbidden();

    $this->postJson(route('schoolpay.webhook', ['token' => str_repeat('a', 48)]), ['payment' => schoolPayPayment('1')])->assertNotFound();

    $this->school->update(['schoolpay_enabled' => false]);
    postSchoolPayWebhook($this->school, schoolPayPayment('18843014'))->assertNotFound();

    expect(SchoolPayTransaction::count())->toBe(0)
        ->and(StudentPayment::count())->toBe(0);
});

it('matches by registration number when the SchoolPay code is unknown', function () {
    postSchoolPayWebhook($this->school, schoolPayPayment('18847257', ['studentPaymentCode' => '1006480152', 'studentRegistrationNumber' => 'HIS-252', 'studentName' => 'Okello Brian']))->assertOk();

    expect(StudentPayment::sole()->student_id)->toBe($this->okello->id);
});

it('keeps an unmatched payment for the bursar, who picks the learner', function () {
    postSchoolPayWebhook($this->school, schoolPayPayment('18850001', ['studentPaymentCode' => '1009999999', 'studentName' => 'Okello B.']))->assertOk();

    $transaction = SchoolPayTransaction::sole();
    expect($transaction->status)->toBe('unmatched')
        ->and(StudentPayment::count())->toBe(0);

    Filament::setCurrentPanel('app');
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id, 'name' => 'Bursar Jane'])->assignRole('School Admin'));

    Livewire::test(ListSchoolPayTransactions::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$transaction])
        ->callAction(TestAction::make('match')->table($transaction), data: ['student_id' => $this->okello->id, 'remember_code' => true])
        ->assertNotified();

    expect($transaction->fresh()->status)->toBe('recorded')
        ->and(StudentPayment::sole())
        ->student_id->toBe($this->okello->id)
        ->recorded_by->toBe('Bursar Jane')
        ->and($this->okello->fresh()->schoolpay_code)->toBe('1009999999');

    // The next payment to that code is recorded by itself.
    postSchoolPayWebhook($this->school, schoolPayPayment('18850002', ['studentPaymentCode' => '1009999999']))->assertOk();
    expect(StudentPayment::count())->toBe(2);
});

it('lists other fees without adding them to the learner\'s school fees', function () {
    postSchoolPayWebhook($this->school, schoolPayPayment('18843597', ['amount' => '150000.00', 'supplementaryFeeDescription' => 'UNIFORM FEES']), 'OTHER_FEES')->assertOk();

    expect(SchoolPayTransaction::sole())
        ->status->toBe('other_fees')
        ->fee_description->toBe('UNIFORM FEES')
        ->and(StudentPayment::count())->toBe(0);
});

it('ignores payments SchoolPay has not completed', function () {
    postSchoolPayWebhook($this->school, schoolPayPayment('18843015', ['transactionCompletionStatus' => 'Pending']))->assertOk();

    expect(SchoolPayTransaction::count())->toBe(0);
});

it('checks SchoolPay for payments the web hook missed', function () {
    Http::fake([
        'schoolpay.co.ug/*' => Http::response([
            'returnCode' => 0,
            'returnMessage' => '2 transaction(s) found',
            'transactions' => [schoolPayPayment('18843014')],
            'supplementaryFeePayments' => [schoolPayPayment('18843597', ['supplementaryFeeDescription' => 'UNIFORM FEES'])],
        ]),
    ]);

    $this->artisan('schoolpay:sync')->assertSuccessful();
    $this->artisan('schoolpay:sync')->assertSuccessful();

    $from = now('Africa/Kampala')->subDays(2)->toDateString();
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), "/SchoolRangeTransactions/809/{$from}/")
        && str_ends_with($request->url(), '/'.strtoupper(md5('809'.$from.'sp-secret'))));

    expect(StudentPayment::count())->toBe(1)
        ->and(SchoolPayTransaction::count())->toBe(2)
        ->and($this->school->fresh()->schoolpay_synced_at)->not->toBeNull()
        ->and($this->school->fresh()->schoolpay_sync_error)->toBeNull();
});

it('notes on the school when SchoolPay refuses the check', function () {
    Http::fake(['schoolpay.co.ug/*' => Http::response(['returnCode' => 1, 'returnMessage' => 'Invalid request hash'])]);

    expect(fn () => app(SchoolPayPayments::class)->sync($this->school->fresh()))
        ->toThrow(RuntimeException::class, 'Invalid request hash');

    expect($this->school->fresh()->schoolpay_sync_error)->toContain('Invalid request hash');
});

it('lets the school set up SchoolPay from its own Settings page', function () {
    Filament::setCurrentPanel('app');
    $this->school->update(['schoolpay_enabled' => false, 'schoolpay_school_code' => null, 'schoolpay_api_password' => null]);
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));

    Livewire::test(SchoolPaySettings::class)
        ->assertOk()
        ->assertSee('How to set it up')
        ->set('data.schoolpay_enabled', true)
        ->set('data.schoolpay_school_code', '809')
        ->set('data.schoolpay_api_password', 'sp-new-secret')
        ->call('save')
        ->assertHasNoErrors()
        ->assertNotified('SchoolPay settings saved');

    $school = $this->school->fresh();

    expect($school->schoolpay_enabled)->toBeTrue()
        ->and($school->schoolpay_school_code)->toBe('809')
        ->and($school->schoolpay_api_password)->toBe('sp-new-secret')
        ->and($school->schoolPayWebhookUrl())->not->toBeNull();

    // Saving again without retyping keeps the password.
    Livewire::test(SchoolPaySettings::class)->call('save')->assertHasNoErrors();

    expect($this->school->fresh()->schoolpay_api_password)->toBe('sp-new-secret');
});

it('keeps the SchoolPay page from staff without Settings', function () {
    Filament::setCurrentPanel('app');
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('Teacher'));

    $this->get(SchoolPaySettings::getUrl())->assertForbidden();
});
