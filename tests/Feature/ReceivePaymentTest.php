<?php

use App\Filament\Pages\ReceivePayment;
use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\Term;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $school = School::create(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($school);
    $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026', 'is_current' => true]);
    $term = Term::create(['school_id' => $school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true]);
    $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'S.2']);
    $this->student = Student::create(['school_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Aisha Nakato', 'admission_no' => 'ADM-001', 'status' => 'active']);
    StudentCharge::create(['school_id' => $school->id, 'student_id' => $this->student->id, 'term_id' => $term->id, 'description' => 'Tuition', 'amount' => 450000, 'charged_on' => '2026-09-14']);

    $this->actingAs(User::factory()->create(['school_id' => $school->id])->assignRole('School Admin'));
});

it('shows what the learner owes under the amount box and on their card', function () {
    Livewire::withQueryParams(['student' => $this->student->id])
        ->test(ReceivePayment::class)
        ->assertSee('Owes UGX 450,000')
        ->assertSee('Balance owed')
        ->assertSee('rp-side is-active', false); // first on a phone, before the amount is typed
});
