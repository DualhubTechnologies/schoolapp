<?php

use App\Filament\Pages\StartTermBilling;
use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\Term;
use App\Models\User;
use App\Services\AttentionItems;
use App\Services\BillingService;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();

    $this->school = School::create(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    $this->term2 = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 2', 'sequence' => 2]);
    $this->term3 = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true]);
    $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'S.1']);

    $this->tuition = FeeStructure::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'term_id' => $this->term2->id, 'name' => 'Tuition', 'frequency' => 'per_term', 'applies_to' => 'all', 'amount' => 500000, 'is_active' => true]);

    $this->student = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'name' => 'Sarah Nakato', 'admission_no' => 'ADM-T001', 'status' => 'active']);

    Filament::setCurrentPanel('app');
    $this->bursar = User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin');
    $this->actingAs($this->bursar);
});

it('knows the current term has not been billed, until it is', function () {
    $billing = app(BillingService::class);

    expect($billing->termNeedsBilling($this->term3))->toBeTrue();

    $billing->billTerm($this->term3);

    expect($billing->termNeedsBilling($this->term3))->toBeFalse()
        ->and($billing->termNeedsBilling($this->term2))->toBeFalse(); // not the current term
});

it('prompts whoever handles fees in the bell', function () {
    expect(collect(AttentionItems::for($this->bursar))->pluck('key'))->toContain('term-billing');

    app(BillingService::class)->billTerm($this->term3);
    AttentionItems::forget($this->bursar);

    expect(collect(AttentionItems::for($this->bursar))->pluck('key'))->not->toContain('term-billing');
});

it('keeps last term\'s fees and bills everyone from the Start the term page', function () {
    Livewire::test(StartTermBilling::class)
        ->assertOk()
        ->assertSee(['Term 3', 'Since Term 2', 'Keep the same fees as last term'])
        ->set('data.mode', 'keep')
        ->set('data.bill_now', true)
        ->call('save')
        ->assertNotified('Term set up');

    expect((float) StudentCharge::where('student_id', $this->student->id)->where('term_id', $this->term3->id)->sum('amount'))->toBe(500000.0)
        ->and(FeeStructure::count())->toBe(1);
});

it('updates a fee for the new term while earlier terms keep their amount on record', function () {
    $changed = app(BillingService::class)->setTermFees($this->term3, [$this->tuition->id => 550000]);

    expect($changed)->toBe(1)
        ->and((float) $this->tuition->fresh()->amount)->toBe(500000.0)
        ->and((float) FeeStructure::termlyFor($this->term2)->sole()->amount)->toBe(500000.0)
        ->and((float) FeeStructure::termlyFor($this->term3)->sole()->amount)->toBe(550000.0);

    // Changing it again within the same term corrects this term's version, no third copy.
    app(BillingService::class)->setTermFees($this->term3, [FeeStructure::termlyFor($this->term3)->sole()->id => 560000]);

    expect(FeeStructure::count())->toBe(2)
        ->and((float) FeeStructure::termlyFor($this->term3)->sole()->amount)->toBe(560000.0);

    app(BillingService::class)->billTerm($this->term3);

    expect((float) StudentCharge::where('student_id', $this->student->id)->where('term_id', $this->term3->id)->sum('amount'))->toBe(560000.0);
});

it('keeps the Start the term page to people with Fees or Finance', function () {
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('Teacher'));

    $this->get(StartTermBilling::getUrl())->assertForbidden();
});

it('bills a learner admitted after the term was billed straight away', function () {
    $billing = app(BillingService::class);
    $newcomer = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'name' => 'Brenda Ainembabazi', 'admission_no' => 'ADM-T002', 'status' => 'active']);

    // Before the term is billed, billing the term will cover them.
    expect($billing->billNewLearner($newcomer))->toBe(0.0);

    $billing->billTerm($this->term3);
    $late = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'name' => 'Peter Mugisha', 'admission_no' => 'ADM-T003', 'status' => 'active']);

    expect($billing->billNewLearner($late))->toBe(500000.0)
        ->and($late->balance())->toBe(500000.0)
        ->and($billing->billNewLearner($late))->toBe(0.0); // never twice
});
