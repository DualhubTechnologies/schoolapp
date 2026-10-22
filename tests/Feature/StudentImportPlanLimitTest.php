<?php

use App\Exceptions\PlanLimitReached;
use App\Filament\App\Resources\Students\Pages\ListStudents;
use App\Filament\Pages\SchoolSubscription;
use App\Models\Guardian;
use App\Models\Plan;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\User;
use App\Services\ParentLogins;
use App\Services\StudentCsvImporter;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary', 'status' => 'active']);

    // A tiny plan: room for 3 active students.
    $this->tiny = Plan::create(['name' => 'Tiny', 'slug' => 'tiny', 'max_students' => 3, 'max_users' => 10, 'price_per_term' => 50000, 'price_per_year' => 135000, 'is_trial' => false, 'is_active' => true, 'sort_order' => 0]);
    SubscriptionManager::startTrial($this->school, $this->tiny);

    $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'P.1']);

    foreach (['ADM-1', 'ADM-2'] as $no) {
        Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'first_name' => 'Existing', 'last_name' => $no, 'admission_no' => $no, 'status' => 'active']);
    }

    $this->admin = User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin');
    $this->actingAs($this->admin);

    // Three new learners: one more than the plan has room for.
    Storage::disk('local')->put('imports/over.csv', "first_name,last_name,admission_no,class\nJoan,Nakato,ADM-3,P.1\nBrian,Okello,ADM-4,P.1\nGrace,Namuli,ADM-5,P.1\n");
    $this->import = StudentImport::create(['school_id' => $this->school->id, 'imported_by' => $this->admin->id, 'file_name' => 'over.csv', 'file_path' => 'imports/over.csv', 'status' => 'pending']);
});

it('says when a file is bigger than the plan, and which plans would fit', function () {
    $result = (new StudentCsvImporter($this->import))->validate();

    expect($result['valid_rows'])->toBe(3)
        ->and($result['plan_room'])->toBe(1)
        ->and($result['plan'])->toMatchArray(['name' => 'Tiny', 'limit' => 3, 'active' => 2, 'needed' => 5, 'over_by' => 2])
        ->and(collect($result['plan']['fitting'])->pluck('name')->all())->toBe(['Starter', 'Standard', 'Premium', 'Enterprise']);
});

it('says nothing about the plan when the file fits', function () {
    $this->tiny->update(['max_students' => 10]);

    expect((new StudentCsvImporter($this->import))->validate()['plan'])->toBeNull();
});

it('imports nothing while the file is bigger than the plan', function () {
    $validation = (new StudentCsvImporter($this->import))->validate();

    Livewire::test(ListStudents::class)
        ->set('importId', $this->import->id)
        ->set('validationResult', $validation)
        ->set('runInBackground', false)
        ->call('startImport')
        ->assertNotified('Too many students for your plan')
        ->assertSet('importStep', 'upload');

    expect(Student::where('school_id', $this->school->id)->count())->toBe(2);
});

it('imports once the school has moved to a plan that fits', function () {
    Livewire::test(ListStudents::class)
        ->set('importId', $this->import->id)
        ->call('recheckImport')
        ->assertSet('validationResult.plan.over_by', 2);

    $this->tiny->update(['max_students' => 10]);

    Livewire::test(ListStudents::class)
        ->set('importId', $this->import->id)
        ->call('recheckImport')
        ->assertSet('validationResult.plan', null)
        ->set('runInBackground', false)
        ->call('startImport');

    expect(Student::where('school_id', $this->school->id)->count())->toBe(5);
});

it('suggests the plan that fits the students being added', function () {
    $page = Livewire::test(SchoolSubscription::class, ['adding' => 400]);

    expect($page->instance()->studentsNeeded())->toBe(402)
        ->and(Plan::find($page->instance()->suggestedPlanId())->name)->toBe('Standard');

    $page->assertSee('You are adding 400 students.');
});

it('lets a school on the free trial have any number of learners', function () {
    $school = School::create(['name' => 'Trial Secondary', 'slug' => 'trial-sec', 'email' => 'trial@example.com', 'school_type' => 'secondary', 'status' => 'active']);
    SubscriptionManager::startTrial($school);

    expect(SubscriptionManager::current($school)->plan->max_students)->toBeNull()
        ->and(SubscriptionManager::roomForStudents($school))->toBeNull();
});

it('gives a parent a login even when the plan\'s staff logins are all used', function () {
    $this->tiny->update(['max_users' => 1]);
    expect(SubscriptionManager::roomForUsers($this->school))->toBe(0);

    $guardian = Guardian::create(['school_id' => $this->school->id, 'name' => 'Akello Rose', 'phone' => '0772555101', 'relationship' => 'mother']);
    $login = app(ParentLogins::class)->createFor($guardian, text: false);

    expect($login['user']->hasRole('Parent'))->toBeTrue()
        ->and(SubscriptionManager::userCount($this->school))->toBe(1);

    expect(fn () => User::factory()->create(['school_id' => $this->school->id]))
        ->toThrow(PlanLimitReached::class);
});

it('keeps existing learners workable when the plan is full, but not new or returning ones', function () {
    $third = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'first_name' => 'Third', 'last_name' => 'Learner', 'admission_no' => 'ADM-9', 'status' => 'active']);
    expect(SubscriptionManager::roomForStudents($this->school))->toBe(0);

    $p2 = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'P.2']);
    $third->update(['first_name' => 'Renamed', 'school_class_id' => $p2->id]);
    expect($third->fresh()->name)->toBe('Renamed Learner');

    $third->update(['status' => 'withdrawn']);
    expect(SubscriptionManager::roomForStudents($this->school))->toBe(1);

    Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'first_name' => 'New', 'last_name' => 'Learner', 'admission_no' => 'ADM-10', 'status' => 'active']);
    expect(fn () => $third->update(['status' => 'active']))->toThrow(PlanLimitReached::class)
        ->and(fn () => Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'first_name' => 'One', 'last_name' => 'More', 'admission_no' => 'ADM-11', 'status' => 'active']))->toThrow(PlanLimitReached::class);
});
