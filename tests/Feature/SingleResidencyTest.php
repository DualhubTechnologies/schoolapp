<?php

use App\Filament\App\Resources\Students\Pages\ListStudents;
use App\Models\ResidencyType;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'secondary', 'boarding_type' => 'boarding']);
    SubscriptionManager::startTrial($this->school);
    $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'S.1']);
    $this->boarding = ResidencyType::create(['school_id' => $this->school->id, 'name' => 'Boarding', 'is_active' => true]);
});

function addLearner(School $school, SchoolClass $class, array $data = []): Student
{
    static $n = 0;
    $n++;

    return Student::create($data + ['school_id' => $school->id, 'school_class_id' => $class->id, 'name' => "Learner {$n}", 'admission_no' => "SR-{$n}", 'status' => 'active']);
}

it('makes every learner of a boarding school a boarder', function () {
    expect(addLearner($this->school, $this->class)->residency_type_id)->toBe($this->boarding->id);
});

it('makes every learner of a day school a day scholar, setting up Day if needed', function () {
    $this->school->update(['boarding_type' => 'day']);

    $learner = addLearner($this->school, $this->class);

    expect($learner->residencyType->name)->toBe('Day');
});

it('leaves the choice to a day and boarding school', function () {
    $this->school->update(['boarding_type' => 'mixed']);

    expect(addLearner($this->school, $this->class)->residency_type_id)->toBeNull();
});

it('gives the learners without a residency the boarding one when the school becomes boarding-only', function () {
    $this->school->update(['boarding_type' => 'mixed']);
    $day = ResidencyType::create(['school_id' => $this->school->id, 'name' => 'Day', 'is_active' => true]);
    $none = addLearner($this->school, $this->class);
    $dayScholar = addLearner($this->school, $this->class, ['residency_type_id' => $day->id]);

    $this->school->update(['boarding_type' => 'boarding']);

    expect($none->fresh()->residency_type_id)->toBe($this->boarding->id)
        ->and($dayScholar->fresh()->residency_type_id)->toBe($day->id);
});

it('admits a learner to a boarding school without asking for the residency', function () {
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));

    Livewire::test(ListStudents::class)
        ->mountAction(CreateAction::class)
        ->assertFormFieldHidden('residency_type_id')
        ->fillForm([
            'first_name' => 'Aisha', 'last_name' => 'Nakato', 'gender' => 'female', 'school_class_id' => $this->class->id,
            'new_guardian_name' => 'Sarah Nakato', 'new_guardian_phone' => '0772 555666', 'new_guardian_relationship' => 'mother',
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect(Student::sole()->residency_type_id)->toBe($this->boarding->id);
});
