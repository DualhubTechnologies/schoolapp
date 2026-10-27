<?php

use App\Filament\Admin\Resources\Schools\Pages\EditSchool;
use App\Filament\App\Resources\Students\Pages\EditStudent;
use App\Filament\App\Resources\Students\Pages\ListStudents;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentPayment;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();

    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);
    SubscriptionManager::startTrial($this->school);
});

it('deletes a student only after the user enters their own password', function () {
    Filament::setCurrentPanel('app');
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
    $student = Student::create(['school_id' => $this->school->id, 'name' => 'Aisha Nakato', 'admission_no' => 'ADM-001', 'status' => 'active']);

    Livewire::test(EditStudent::class, ['record' => $student->getRouteKey()])
        ->callAction('delete', data: ['password' => '']);
    expect(Student::find($student->id))->not->toBeNull();

    Livewire::test(EditStudent::class, ['record' => $student->getRouteKey()])
        ->callAction('delete', data: ['password' => 'not-my-password']);
    expect(Student::find($student->id))->not->toBeNull();

    Livewire::test(EditStudent::class, ['record' => $student->getRouteKey()])
        ->callAction('delete', data: ['password' => 'password']);
    expect(Student::find($student->id))->toBeNull();
});

it('deletes a school only after the platform owner enters their password', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create()->assignRole('Super Admin'));

    Livewire::test(EditSchool::class, ['record' => $this->school->getRouteKey()])
        ->callAction('delete', data: ['password' => 'wrong']);
    expect(School::find($this->school->id))->not->toBeNull();

    Livewire::test(EditSchool::class, ['record' => $this->school->getRouteKey()])
        ->callAction('delete', data: ['password' => 'password']);
    expect(School::find($this->school->id))->toBeNull();
});

it('never deletes a learner who has fees records, from the page, the list or anywhere else', function () {
    Filament::setCurrentPanel('app');
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
    $paid = Student::create(['school_id' => $this->school->id, 'name' => 'Aisha Nakato', 'admission_no' => 'ADM-001', 'status' => 'active']);
    $voided = Student::create(['school_id' => $this->school->id, 'name' => 'Brian Okello', 'admission_no' => 'ADM-002', 'status' => 'active']);
    $none = Student::create(['school_id' => $this->school->id, 'name' => 'Entered By Mistake', 'admission_no' => 'ADM-003', 'status' => 'active']);

    StudentPayment::create(['school_id' => $this->school->id, 'student_id' => $paid->id, 'amount' => 50000, 'paid_on' => today(), 'method' => 'cash']);
    StudentPayment::create(['school_id' => $this->school->id, 'student_id' => $voided->id, 'amount' => 10000, 'paid_on' => today(), 'method' => 'cash'])
        ->forceFill(['voided_at' => now(), 'void_reason' => 'Typed twice'])->save();

    Livewire::test(EditStudent::class, ['record' => $paid->getRouteKey()])->assertActionDisabled('delete');
    expect(fn () => $paid->delete())->toThrow(RuntimeException::class, 'has fees records');

    Livewire::test(ListStudents::class)
        ->callTableBulkAction('delete', [$paid, $voided, $none], data: ['password' => 'password'])
        ->assertNotified('1 learner deleted');

    expect(Student::find($paid->id))->not->toBeNull()
        ->and(Student::find($voided->id))->not->toBeNull()
        ->and(Student::find($none->id))->toBeNull();
});
