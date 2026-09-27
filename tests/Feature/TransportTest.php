<?php

use App\Filament\App\Resources\TransportLearners\Pages\ManageTransportLearners;
use App\Filament\App\Resources\TransportRoutes\Pages\ManageTransportRoutes;
use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\Term;
use App\Models\TransportRoute;
use App\Models\User;
use App\Services\BillingService;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    $this->term = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true]);
    $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'P.4']);

    $this->kakiri = TransportRoute::create(['school_id' => $this->school->id, 'name' => 'Kakiri', 'fare' => 15000, 'one_way_fare' => 8000]);
    $this->kamba = TransportRoute::create(['school_id' => $this->school->id, 'name' => 'Kamba', 'fare' => 13000]);
});

function learner(array $attributes = []): Student
{
    static $n = 0;
    $n++;

    return Student::create(array_merge([
        'school_id' => test()->school->id,
        'school_class_id' => test()->class->id,
        'name' => "Learner {$n}",
        'first_name' => 'Learner',
        'last_name' => (string) $n,
        'admission_no' => "ADM-{$n}",
        'gender' => 'female',
        'status' => 'active',
    ], $attributes));
}

function transportCharges(Student $student)
{
    return StudentCharge::where('student_id', $student->id)->whereNotNull('transport_route_id')->get();
}

it('bills the route fare to learners on the van', function () {
    $both = learner(['transport_route_id' => $this->kakiri->id]);
    $oneWay = learner(['transport_route_id' => $this->kakiri->id, 'transport_trip' => 'one_way']);
    $kamba = learner(['transport_route_id' => $this->kamba->id, 'transport_trip' => 'one_way']);

    app(BillingService::class)->billTerm($this->term);

    expect(transportCharges($both)->sole()->only(['description', 'amount']))
        ->toBe(['description' => 'Transport — Kakiri', 'amount' => '15000.00'])
        ->and((float) transportCharges($oneWay)->sole()->amount)->toBe(8000.0)
        ->and(transportCharges($oneWay)->sole()->description)->toBe('Transport — Kakiri (one way)')
        // No one-way price on Kamba: one way pays the full fare.
        ->and((float) transportCharges($kamba)->sole()->amount)->toBe(13000.0);
});

it('charges nothing for learners brought by their parents', function () {
    $walker = learner();

    app(BillingService::class)->billTerm($this->term);

    expect(transportCharges($walker))->toBeEmpty()
        ->and($walker->fresh()->transport_trip)->toBeNull();
});

it('bills the van once per term, even when billing runs again', function () {
    $student = learner(['transport_route_id' => $this->kakiri->id]);
    $billing = app(BillingService::class);

    $billing->billTerm($this->term);
    $billing->billTerm($this->term);

    expect(transportCharges($student))->toHaveCount(1);
});

it('does not bill a route that is out of use', function () {
    $this->kakiri->update(['is_active' => false]);
    $student = learner(['transport_route_id' => $this->kakiri->id]);

    app(BillingService::class)->billTerm($this->term);

    expect(transportCharges($student))->toBeEmpty();
});

it('clears the trip when a learner comes off the van', function () {
    $student = learner(['transport_route_id' => $this->kakiri->id, 'transport_trip' => 'one_way']);

    $student->update(['transport_route_id' => null]);

    expect($student->fresh()->transport_trip)->toBeNull();
});

it('lets the school manage only its own routes', function () {
    $other = School::create(['name' => 'Other School', 'slug' => 'other', 'email' => 'other@example.com']);
    $theirs = TransportRoute::create(['school_id' => $other->id, 'name' => 'Wakiso', 'fare' => 20000]);

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
    Filament::setCurrentPanel('app');

    Livewire::test(ManageTransportRoutes::class)
        ->assertCanSeeTableRecords([$this->kakiri, $this->kamba])
        ->assertCanNotSeeTableRecords([$theirs])
        ->callAction('create', data: ['name' => 'Kikandwa', 'fare' => 15000, 'is_active' => true])
        ->assertHasNoActionErrors();

    expect(TransportRoute::where('name', 'Kikandwa')->sole()->school_id)->toBe($this->school->id);
});

it('adds, moves and takes learners off the van from the Transport module', function () {
    $amina = learner();
    $brian = learner();
    $alreadyOn = learner(['transport_route_id' => $this->kamba->id]);

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
    Filament::setCurrentPanel('app');

    Livewire::test(ManageTransportLearners::class)
        ->assertCanSeeTableRecords([$alreadyOn])
        ->assertCanNotSeeTableRecords([$amina, $brian])
        ->callAction('addLearners', data: [
            'student_ids' => [$amina->id, $brian->id, $alreadyOn->id],
            'transport_route_id' => $this->kakiri->id,
            'transport_trip' => 'one_way',
        ])
        ->assertHasNoActionErrors();

    expect($amina->fresh()->only(['transport_route_id', 'transport_trip']))->toBe(['transport_route_id' => $this->kakiri->id, 'transport_trip' => 'one_way'])
        // Someone already on the van is moved from the table, not by "add".
        ->and($alreadyOn->fresh()->transport_route_id)->toBe($this->kamba->id);

    Livewire::test(ManageTransportLearners::class)
        ->callTableAction('changeRoute', $amina, data: ['transport_route_id' => $this->kamba->id, 'transport_trip' => 'both'])
        ->callTableAction('takeOff', $brian);

    expect($amina->fresh()->transport_route_id)->toBe($this->kamba->id)
        ->and($brian->fresh()->transport_route_id)->toBeNull();
});

it('is its own module, separate from fees', function () {
    $feesOnly = User::factory()->create(['school_id' => $this->school->id, 'modules' => ['fees']])->assignRole('Accountant');
    $transportOnly = User::factory()->create(['school_id' => $this->school->id, 'modules' => ['transport']])->assignRole('Staff');

    $this->withoutVite();

    $this->actingAs($feesOnly)->get('/transport-routes')->assertForbidden();
    $this->actingAs($feesOnly)->get('/transport-learners')->assertForbidden();

    $this->actingAs($transportOnly)->get('/transport-routes')->assertOk()->assertSee('Kakiri');
    $this->actingAs($transportOnly)->get('/transport-learners')->assertOk();
});
