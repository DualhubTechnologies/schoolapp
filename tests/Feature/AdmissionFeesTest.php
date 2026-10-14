<?php

use App\Filament\App\Resources\Students\Pages\ListStudents;
use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\Guardian;
use App\Models\ResidencyType;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\Term;
use App\Models\User;
use App\Services\BillingService;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    $this->term1 = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 1', 'sequence' => 1, 'start_date' => '2026-02-02', 'end_date' => '2026-05-01']);
    $this->term2 = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 2', 'sequence' => 2, 'start_date' => '2026-05-25', 'end_date' => '2026-08-22', 'is_current' => true]);
    $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'S.1']);
    $this->day = ResidencyType::create(['school_id' => $this->school->id, 'name' => 'Day', 'is_active' => true]);

    $fee = fn (array $data) => FeeStructure::create($data + ['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'applies_to' => 'all', 'is_active' => true]);
    $fee(['name' => 'Tuition', 'frequency' => 'per_term', 'term_id' => $this->term1->id, 'amount' => 800000]);
    $fee(['name' => 'Admission', 'frequency' => 'once', 'applies_to' => 'new_only', 'amount' => 100000]);
    $fee(['name' => 'Development', 'frequency' => 'once', 'amount' => 150000]); // made before "who pays" followed "how often"
    $fee(['name' => 'Orientation', 'frequency' => 'per_term', 'applies_to' => 'new_only', 'term_id' => $this->term1->id, 'amount' => 20000]);

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
});

function admittedLearner(array $data = []): Student
{
    static $n = 0;
    $n++;

    return Student::create([
        'school_id' => test()->school->id, 'school_class_id' => test()->class->id, 'residency_type_id' => test()->day->id,
        'name' => "Learner {$n}", 'admission_no' => "ADM-{$n}", 'status' => 'active', 'admission_date' => '2025-02-03', ...$data,
    ]);
}

function billedFees(Student $student, ?Term $term = null): array
{
    return StudentCharge::where('student_id', $student->id)
        ->when($term, fn ($q) => $q->where('term_id', $term->id))
        ->orderBy('description')->pluck('description')->all();
}

it('never charges admission fees to the learners a school already had', function () {
    $continuing = admittedLearner(['is_new_learner' => false]);

    app(BillingService::class)->billTerm($this->term2);

    expect(billedFees($continuing))->toBe(['Tuition']);
});

it('charges a new learner the admission fees once, in their first term only', function () {
    $new = admittedLearner(['is_new_learner' => true, 'admission_date' => '2026-05-10']);
    $billing = app(BillingService::class);

    $billing->billTerm($this->term2);
    $billing->billTerm($this->term2);

    expect(billedFees($new, $this->term2))->toBe(['Admission', 'Development', 'Orientation', 'Tuition']);

    $term3 = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $this->term2->academic_year_id, 'name' => 'Term 3', 'sequence' => 3, 'start_date' => '2026-09-14', 'end_date' => '2026-12-04']);
    $billing->billTerm($term3);

    expect(billedFees($new, $term3))->toBe(['Tuition']);
});

it('counts a learner admitted in the holiday before the term as new', function () {
    // Admitted on 20 May for a term starting 25 May: the old rule (admitted
    // within the term's dates) missed them.
    $new = admittedLearner(['is_new_learner' => true, 'admission_date' => '2026-05-20']);

    app(BillingService::class)->billTerm($this->term2);

    expect(billedFees($new))->toContain('Admission');
});

it('still charges the admission fees when a new learner is first billed a term late', function () {
    $new = admittedLearner(['is_new_learner' => true, 'admission_date' => '2026-03-01']);

    app(BillingService::class)->billTerm($this->term2);

    expect(billedFees($new))->toContain('Admission')->toContain('Development');
});

it('admits a learner as new, and bills the admission fees when the term is already billed', function () {
    admittedLearner(['is_new_learner' => false]);
    app(BillingService::class)->billTerm($this->term2);

    Livewire::test(ListStudents::class)
        ->callAction(CreateAction::class, data: [
            'first_name' => 'Aisha', 'last_name' => 'Nakato', 'gender' => 'female',
            'school_class_id' => $this->class->id, 'residency_type_id' => $this->day->id,
            'new_guardian_name' => 'Sarah Nakato', 'new_guardian_phone' => '0772555666',
        ])
        ->assertHasNoFormErrors();

    $aisha = Student::where('first_name', 'Aisha')->sole();

    expect($aisha->is_new_learner)->toBeTrue()
        ->and(billedFees($aisha))->toBe(['Admission', 'Development', 'Orientation', 'Tuition']);
});

it('lets the bursar mark learners as continuing so they do not pay admission fees', function () {
    $guardian = Guardian::create(['school_id' => $this->school->id, 'name' => 'Sarah Nakato', 'phone' => '0772555666']);
    $imported = admittedLearner(['is_new_learner' => true, 'guardian_id' => $guardian->id]);

    Livewire::test(ListStudents::class)
        ->callTableBulkAction('markContinuing', [$imported]);

    app(BillingService::class)->billTerm($this->term2);

    expect($imported->fresh()->is_new_learner)->toBeFalse()
        ->and(billedFees($imported))->toBe(['Tuition']);
});

it('makes an admission fee one for new learners whatever was picked under who pays', function () {
    expect(FeeStructure::withWhoPays(['frequency' => 'once', 'applies_to' => 'all'])['applies_to'])->toBe('new_only')
        ->and(FeeStructure::withWhoPays(['frequency' => 'on_demand', 'applies_to' => 'new_only'])['applies_to'])->toBe('all')
        ->and(FeeStructure::withWhoPays(['frequency' => 'per_term', 'applies_to' => 'new_only'])['applies_to'])->toBe('new_only');
});
