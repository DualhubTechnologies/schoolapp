<?php

use App\Filament\App\Resources\Guardians\GuardianResource;
use App\Filament\App\Resources\Students\Pages\ListStudents;
use App\Filament\App\Resources\Students\StudentResource;
use App\Filament\Pages\EnterMarks;
use App\Filament\Pages\ReceivePayment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($this->school);
    $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'S.1']);
    $this->student = Student::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'name' => 'Brenda Ainembabazi', 'admission_no' => '003', 'status' => 'active']);
});

it('lets a teacher look learners up but not admit, change or remove them', function () {
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('Teacher'));

    expect(StudentResource::canAccess())->toBeTrue()
        ->and(StudentResource::canCreate())->toBeFalse()
        ->and(StudentResource::canEdit($this->student))->toBeFalse()
        ->and(StudentResource::canDeleteAny())->toBeFalse()
        ->and(GuardianResource::canCreate())->toBeFalse();

    $this->get(StudentResource::getUrl('view', ['record' => $this->student]))->assertOk()
        ->assertSee('Only the admissions office can change these details.')
        ->assertDontSee('Receive payment');
    $this->get(StudentResource::getUrl('edit', ['record' => $this->student]))->assertForbidden();

    Livewire::test(ListStudents::class)
        ->assertActionHidden('create')
        ->assertActionHidden('importStudents');
});

it('lets the admissions office admit and change learners', function () {
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id, 'modules' => ['admissions', 'id_cards']])->assignRole('Staff'));

    expect(StudentResource::canAccess())->toBeTrue()
        ->and(StudentResource::canCreate())->toBeTrue()
        ->and(StudentResource::canEdit($this->student))->toBeTrue();

    $this->get(StudentResource::getUrl('edit', ['record' => $this->student]))->assertOk();
});

it('has an Admissions role for the admissions office, without fees or marks', function () {
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('Admissions'));

    expect(StudentResource::canCreate())->toBeTrue()
        ->and(GuardianResource::canCreate())->toBeTrue()
        ->and(ReceivePayment::canAccess())->toBeFalse()
        ->and(EnterMarks::canAccess())->toBeFalse();

    $this->get(route('filament.app.pages.dashboard'))->assertOk()
        ->assertSee('Admissions &amp; records', false)
        ->assertSee('Admit a learner');
});
