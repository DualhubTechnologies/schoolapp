<?php

use App\Filament\App\Resources\Students\Pages\ListStudents;
use App\Filament\Pages\StudentAccount;
use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true, 'start_date' => now()->subDays(20)->toDateString(), 'end_date' => now()->addDays(40)->toDateString()]);
    $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'P.4']);
    $this->orphan = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'name' => 'Brian Okello', 'admission_no' => 'LG-1', 'status' => 'active']);

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
});

it('adds a new parent to a learner from the students list', function () {
    Livewire::test(ListStudents::class)
        ->callAction(TestAction::make('linkGuardian')->table($this->orphan), data: [
            'new_guardian_name' => 'Okello Peter',
            'new_guardian_phone' => '0772 123456',
            'new_guardian_relationship' => 'father',
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Parent linked');

    $parent = $this->orphan->fresh()->guardian;
    expect($parent->name)->toBe('Okello Peter')
        ->and($parent->phone)->toBe('0772 123456')
        ->and($parent->relationship)->toBe('father')
        ->and($parent->school_id)->toBe($this->school->id);
});

it('links a learner to a family already at the school, without a duplicate parent', function () {
    $mother = Guardian::create(['school_id' => $this->school->id, 'name' => 'Nakato Sarah', 'phone' => '0772555666']);

    Livewire::test(ListStudents::class)
        ->callAction(TestAction::make('linkGuardian')->table($this->orphan), data: ['guardian_id' => $mother->id])
        ->assertHasNoActionErrors();

    expect($this->orphan->fresh()->guardian_id)->toBe($mother->id);

    $sister = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'name' => 'Aisha Okello', 'admission_no' => 'LG-2', 'status' => 'active']);
    Livewire::test(ListStudents::class)
        ->callAction(TestAction::make('linkGuardian')->table($sister), data: ['new_guardian_name' => 'Sarah N.', 'new_guardian_phone' => '+256 772 555666']);

    expect($sister->fresh()->guardian_id)->toBe($mother->id)
        ->and(Guardian::count())->toBe(1);
});

it('offers to add the parent on the student account page', function () {
    Livewire::withQueryParams(['student' => $this->orphan->id])
        ->test(StudentAccount::class)
        ->assertActionVisible('linkGuardian')
        ->callAction('linkGuardian', data: ['new_guardian_name' => 'Okello Peter', 'new_guardian_phone' => '0772123456'])
        ->assertHasNoActionErrors();

    expect($this->orphan->fresh()->guardian?->name)->toBe('Okello Peter');

    Livewire::withQueryParams(['student' => $this->orphan->id])
        ->test(StudentAccount::class)
        ->assertActionHidden('linkGuardian');
});
