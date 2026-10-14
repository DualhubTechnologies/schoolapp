<?php

use App\Filament\Pages\StudentAccount;
use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\StudentPayment;
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

    $school = School::create([
        'name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'secondary',
        'fee_payment_bank' => 'Centenary Bank, A/C 3100012345', 'fee_payment_mobile_money' => 'MTN MoMo Pay 123456',
    ]);
    SubscriptionManager::startTrial($school);
    $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026', 'is_current' => true]);
    $term2 = Term::create(['school_id' => $school->id, 'academic_year_id' => $year->id, 'name' => 'Term 2', 'sequence' => 2]);
    $this->term = Term::create(['school_id' => $school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true]);
    $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'S.2']);
    $this->student = Student::create(['school_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Aisha Nakato', 'admission_no' => 'ADM-001', 'status' => 'active', 'schoolpay_code' => '1004567890']);

    $charge = fn (Term $term, string $item, int $amount, string $on) => StudentCharge::create(['school_id' => $school->id, 'student_id' => $this->student->id, 'term_id' => $term->id, 'description' => $item, 'amount' => $amount, 'charged_on' => $on]);
    $charge($term2, 'Tuition', 800000, '2026-05-25');
    StudentPayment::create(['school_id' => $school->id, 'student_id' => $this->student->id, 'term_id' => $term2->id, 'amount' => 700000, 'paid_on' => '2026-06-01', 'method' => 'bank']);
    $charge($this->term, 'Tuition', 850000, '2026-09-14');
    $charge($this->term, 'Lunch', 150000, '2026-09-14');
    StudentPayment::create(['school_id' => $school->id, 'student_id' => $this->student->id, 'term_id' => $this->term->id, 'amount' => 600000, 'paid_on' => '2026-09-20', 'method' => 'bank']);

    $this->actingAs(User::factory()->create(['school_id' => $school->id])->assignRole('School Admin'));
});

it('itemises the term on the invoice, with what was owed before, and says how to pay', function () {
    Livewire::withQueryParams(['student' => $this->student->id])
        ->test(StudentAccount::class)
        ->call('setMode', 'invoice')
        ->assertSeeInOrder(['Balance from earlier terms', '100,000', 'Tuition', '850,000', 'Lunch', '150,000'])
        ->assertSeeInOrder(['Fees for Term 3', '1,000,000', 'Less payments received', '600,000', 'Balance due', 'UGX 500,000'])
        ->assertSee('1004567890')
        ->assertSee('Centenary Bank, A/C 3100012345')
        ->assertSee('MTN MoMo Pay 123456')
        ->assertSee('Quote admission number');
});
