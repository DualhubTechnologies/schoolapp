<?php

use App\Filament\Pages\ReceivePayment;
use App\Filament\Pages\ReportCards;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Guardian;
use App\Models\Mark;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\StudentPayment;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\FeeReminderService;
use App\Services\ParentMessages;
use App\Services\SmsSender;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary', 'phone' => '0772000111']);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    $this->term = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true]);
    $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'P.4']);
    $guardian = Guardian::create(['school_id' => $this->school->id, 'name' => 'Sarah Nakato', 'phone' => '0772555666']);

    $this->student = Student::create([
        'school_id' => $this->school->id, 'school_class_id' => $class->id, 'guardian_id' => $guardian->id,
        'name' => 'Aisha Nakato', 'first_name' => 'Aisha', 'last_name' => 'Nakato',
        'admission_no' => 'ADM-001', 'gender' => 'female', 'status' => 'active',
    ]);

    StudentCharge::create(['school_id' => $this->school->id, 'student_id' => $this->student->id, 'term_id' => $this->term->id, 'description' => 'Tuition', 'amount' => 200000, 'charged_on' => today()]);

    $this->admin = User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin');
});

it('shows a family their balance, statement and receipts from the short link, without signing in', function () {
    $payment = StudentPayment::create(['school_id' => $this->school->id, 'student_id' => $this->student->id, 'term_id' => $this->term->id, 'amount' => 150000, 'paid_on' => today(), 'method' => 'cash']);

    $url = $this->student->parentPageUrl();

    expect(strlen(basename($url)))->toBe(10)
        ->and($this->student->fresh()->parentPageUrl())->toBe($url);

    $this->get($url)
        ->assertOk()
        ->assertSee('Aisha Nakato')
        ->assertSee('UGX 50,000')
        ->assertSee('Tuition')
        ->assertSee($payment->receipt_no);

    $this->get(route('parent.receipt', ['token' => $this->student->parent_token, 'payment' => $payment->id]))->assertOk();

    expect(auth()->check())->toBeFalse();
});

it('refuses unknown links and other learners\' receipts', function () {
    $this->get('/p/notarealtoken')->assertNotFound();

    $other = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->student->school_class_id, 'name' => 'Brian Okello', 'admission_no' => 'ADM-002', 'status' => 'active']);
    $theirs = StudentPayment::create(['school_id' => $this->school->id, 'student_id' => $other->id, 'amount' => 1000, 'paid_on' => today(), 'method' => 'cash']);

    $this->student->parentPageUrl();

    $this->get(route('parent.receipt', ['token' => $this->student->parent_token, 'payment' => $theirs->id]))->assertNotFound();
});

it('lists report cards only once the school shares them', function () {
    $this->student->parentPageUrl();
    $assessment = Assessment::create(['school_id' => $this->school->id, 'term_id' => $this->term->id, 'name' => 'End of term', 'type' => 'eot', 'max_score' => 100]);
    $subject = Subject::create(['school_id' => $this->school->id, 'name' => 'English', 'curriculum' => 'primary']);
    $this->student->schoolClass->subjects()->attach($subject->id);
    Mark::create(['assessment_id' => $assessment->id, 'student_id' => $this->student->id, 'subject_id' => $subject->id, 'score' => 70]);

    $this->get($this->student->parentPageUrl())->assertDontSee('Report cards');
    $this->get(route('parent.report', ['token' => $this->student->parent_token, 'term' => $this->term->id]))->assertNotFound();

    $this->actingAs($this->admin);
    $sms = $this->mock(SmsSender::class);
    $sms->shouldReceive('send')->once()->withArgs(fn ($phone, $message) => str_contains($message, 'report card is ready') && str_contains($message, '/p/'))->andReturn(['ok' => true, 'ref' => 'x', 'error' => null]);

    Livewire::test(ReportCards::class)->callAction('shareWithParents', ['text_parents' => true]);

    auth()->logout();
    expect($this->term->fresh()->report_cards_released_at)->not->toBeNull();

    $this->get($this->student->parentPageUrl())->assertSee('Report cards')->assertSee('Term 3');
    $this->get(route('parent.report', ['token' => $this->student->parent_token, 'term' => $this->term->id]))->assertOk()->assertSee('Aisha Nakato');
});

it('fills the full balance on request and texts the receipt with the new balance', function () {
    $this->actingAs($this->admin);

    $sms = $this->mock(SmsSender::class);
    $sms->shouldReceive('send')->once()
        ->withArgs(fn ($phone, $message) => $phone === '0772555666'
            && str_contains($message, 'Received UGX 150,000 for Aisha Nakato (P.4)')
            && str_contains($message, 'Balance UGX 50,000')
            && str_contains($message, '/p/'))
        ->andReturn(['ok' => true, 'ref' => 'x', 'error' => null]);

    Livewire::test(ReceivePayment::class)
        ->fillForm(['student_id' => $this->student->id])
        ->assertFormSet(['amount' => null, 'payer_phone' => '0772555666'])
        ->call('fillBalance')
        ->assertFormSet(['amount' => 200000])
        ->fillForm(['amount' => 150000])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($this->student->balance())->toBe(50000.0);
});

it('texts parents in Luganda when the school chooses it', function () {
    $this->school->update(['parent_sms_language' => 'lg']);
    $payment = StudentPayment::create(['school_id' => $this->school->id, 'student_id' => $this->student->id, 'amount' => 200000, 'paid_on' => today(), 'method' => 'cash']);

    expect(app(ParentMessages::class)->receipt($payment->fresh()))->toContain('Tufunye UGX 200,000')->toContain('Ebisale biweddeyo')
        ->and(FeeReminderService::defaultTemplate($this->school->fresh()))->toBe(FeeReminderService::LUGANDA_TEMPLATE);
});

it('tells parents the learner\'s SchoolPay code where they are told how to pay', function () {
    $this->student->update(['schoolpay_code' => '1004567890']);

    $this->get($this->student->parentPageUrl())->assertSee('SchoolPay code')->assertSee('1004567890');

    $message = app(FeeReminderService::class)->message($this->student->fresh(), 200000, null, '2026-10-15');
    expect($message)->toContain('Pay via SchoolPay code 1004567890.');

    $this->school->update(['parent_sms_language' => 'lg']);
    expect(app(FeeReminderService::class)->message($this->student->fresh(), 200000))->toContain('Sasula ku SchoolPay code 1004567890.');
});

it('leaves SchoolPay out for learners without a code', function () {
    expect(app(FeeReminderService::class)->message($this->student, 200000))->not->toContain('SchoolPay');
});
