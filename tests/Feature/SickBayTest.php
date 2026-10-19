<?php

use App\Filament\App\Resources\SickBayMedicines\SickBayMedicineResource;
use App\Filament\App\Resources\SickBayVisits\Pages\ManageSickBayVisits;
use App\Filament\App\Resources\SickBayVisits\SickBayVisitResource;
use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SickBayMedicine;
use App\Models\SickBayVisit;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Services\ParentMessages;
use App\Services\SchoolInsights;
use App\Services\Subscriptions\SubscriptionManager;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-10-19 09:00:00');
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'phone' => '0414000111', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    $this->term = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true, 'start_date' => '2026-09-14', 'end_date' => '2026-12-04']);
    $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'S.2']);

    $guardian = Guardian::create(['school_id' => $this->school->id, 'name' => 'Nakato Sarah', 'phone' => '0772123456']);
    $this->patient = Student::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'guardian_id' => $guardian->id, 'name' => 'Aisha Nakato', 'admission_no' => 'SB-001', 'status' => 'active']);

    $this->nurse = User::factory()->create(['school_id' => $this->school->id, 'name' => 'Nurse Joy'])->assignRole('Nurse');
    $this->actingAs($this->nurse);
});

it('records a learner sent home and texts the parent', function () {
    Livewire::test(ManageSickBayVisits::class)
        ->callAction('create', data: [
            'who' => 'learner',
            'student_id' => $this->patient->id,
            'visited_at' => '2026-10-19 08:40',
            'temperature' => 38.6,
            'complaint' => 'Fever',
            'diagnosis' => 'Suspected malaria',
            'outcome' => 'sent_home',
            'notify_parent' => true,
        ])
        ->assertHasNoActionErrors();

    $visit = SickBayVisit::sole();
    expect($visit->term_id)->toBe($this->term->id)
        ->and($visit->attended_by)->toBe('Nurse Joy')
        ->and($visit->hasFever())->toBeTrue()
        ->and($visit->parent_notified_at)->not->toBeNull();

    expect(app(ParentMessages::class)->sickBayVisit($visit))
        ->toContain('Aisha Nakato')
        ->toContain('Fever')
        ->toContain('0414000111');
});

it('does not text the parent for a minor visit unless asked', function () {
    Livewire::test(ManageSickBayVisits::class)
        ->callAction('create', data: ['who' => 'learner', 'student_id' => $this->patient->id, 'visited_at' => '2026-10-19 08:40', 'complaint' => 'Headache', 'outcome' => 'returned_to_class'])
        ->assertHasNoActionErrors();

    expect(SickBayVisit::sole()->parent_notified_at)->toBeNull();
});

it('takes medicine given out of stock, and puts it back if the visit is deleted', function () {
    $panadol = SickBayMedicine::create(['school_id' => $this->school->id, 'name' => 'Paracetamol 500mg', 'unit' => 'tablets', 'stock' => 100, 'reorder_level' => 20, 'is_active' => true]);

    Livewire::test(ManageSickBayVisits::class)
        ->callAction('create', data: [
            'who' => 'learner',
            'student_id' => $this->patient->id,
            'visited_at' => '2026-10-19 08:40',
            'complaint' => 'Headache',
            'outcome' => 'resting',
            'dispensed' => [['sick_bay_medicine_id' => $panadol->id, 'quantity' => 6, 'dosage' => '2 tabs, 3 times a day']],
        ])
        ->assertHasNoActionErrors();

    $visit = SickBayVisit::sole();
    expect($visit->dispensed)->toHaveCount(1)
        ->and($panadol->fresh()->stock)->toBe(94);

    $visit->dispensed()->first()->update(['quantity' => 4]);
    expect($panadol->fresh()->stock)->toBe(96);

    $visit->dispensed()->first()->delete();
    expect($panadol->fresh()->stock)->toBe(100);
});

it('shows who is resting, then lets them leave the sick bay', function () {
    $visit = SickBayVisit::create(['school_id' => $this->school->id, 'student_id' => $this->patient->id, 'term_id' => $this->term->id, 'visited_at' => now()->subHour(), 'complaint' => 'Stomach ache', 'outcome' => 'resting']);
    $admin = User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin');
    $this->actingAs($admin);

    $findings = collect((new SchoolInsights($this->school->id, CarbonImmutable::parse('2026-10-19')))->findings())->keyBy('key');
    expect($findings['sick-bay']['title'])->toBe('1 learner is resting in the sick bay')
        ->and($findings['sick-bay']['detail'])->toContain('Aisha Nakato');

    Livewire::test(ManageSickBayVisits::class)
        ->callAction(TestAction::make('discharge')->table($visit), data: ['outcome' => 'returned_to_class'])
        ->assertHasNoActionErrors();

    expect($visit->fresh()->discharged_at)->not->toBeNull()
        ->and($visit->fresh()->outcome)->toBe('returned_to_class')
        ->and(SickBayVisit::inSickBay()->count())->toBe(0);
});

it('opens the sick bay to the nurse and not to a teacher', function () {
    $this->get(SickBayVisitResource::getUrl())->assertOk();
    $this->get(SickBayMedicineResource::getUrl())->assertOk();

    $teacher = User::factory()->create(['school_id' => $this->school->id])->assignRole('Teacher');
    $this->actingAs($teacher)->get(SickBayVisitResource::getUrl())->assertForbidden();
});
