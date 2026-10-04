<?php

use App\Filament\Admin\Resources\Schools\Pages\EditSchool;
use App\Filament\App\Resources\Students\Pages\EditStudent;
use App\Models\School;
use App\Models\Student;
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
