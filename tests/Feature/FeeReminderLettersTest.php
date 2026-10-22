<?php

use App\Filament\App\Resources\FeeBalances\Pages\ListFeeBalances;
use App\Filament\App\Resources\FeeReminders\Pages\ListFeeReminders;
use App\Models\AcademicYear;
use App\Models\FeeReminder;
use App\Models\Guardian;
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

    $this->school = School::create(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    $term = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true]);
    $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'S.3']);
    $this->student = Student::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'name' => 'Agnes Lwanga', 'admission_no' => 'DHS/2026/0422', 'status' => 'active']);
    StudentCharge::create(['school_id' => $this->school->id, 'student_id' => $this->student->id, 'term_id' => $term->id, 'description' => 'Tuition', 'amount' => 450000, 'charged_on' => '2026-09-14']);

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
});

it('tells the parent where to pay: the school\'s bank, mobile money and the learner\'s SchoolPay code', function () {
    $this->school->update([
        'fee_payment_bank' => 'Stanbic Bank, A/C 9030012345678, Hope Secondary School',
        'fee_payment_mobile_money' => 'MTN 0772 000111 (Hope Secondary)',
    ]);
    $this->student->update(['schoolpay_code' => '1004567890']);

    $this->get(route('filament.app.fees.letters', ['students' => $this->student->id]))->assertOk()
        ->assertSee('UGX 450,000')
        ->assertSee('Stanbic Bank, A/C 9030012345678, Hope Secondary School')
        ->assertSee('MTN 0772 000111 (Hope Secondary)')
        ->assertSee('Pay with code 1004567890')
        ->assertSee('DHS/2026/0422')
        ->assertSee('as soon as possible')
        ->assertDontSee("The bursar's office.", false);

    $this->get(route('filament.app.fees.letters', ['students' => $this->student->id, 'deadline' => '2026-11-05']))
        ->assertSee('by <strong>5 Nov 2026</strong>', false);
});

it('does not mention ways of paying the school has not set up', function () {
    $this->get(route('filament.app.fees.letters', ['students' => $this->student->id]))->assertOk()
        ->assertSee("The bursar's office.", false)
        ->assertDontSee('SchoolPay')
        ->assertDontSee('Mobile money');
});

it('reminds only learners who have paid less than the chosen share of their fees', function () {
    $class = $this->student->schoolClass;
    $term = Term::current();
    $learner = function (string $name, string $phone, float $paid) use ($class, $term): Student {
        $guardian = Guardian::create(['school_id' => $this->school->id, 'name' => "Parent of {$name}", 'phone' => $phone]);
        $student = Student::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'guardian_id' => $guardian->id, 'name' => $name, 'admission_no' => 'P-'.$phone, 'status' => 'active']);
        StudentCharge::create(['school_id' => $this->school->id, 'student_id' => $student->id, 'term_id' => $term->id, 'description' => 'Tuition', 'amount' => 500000, 'charged_on' => '2026-09-14']);
        StudentPayment::create(['school_id' => $this->school->id, 'student_id' => $student->id, 'amount' => $paid, 'paid_on' => '2026-09-20', 'method' => 'cash']);

        return $student;
    };
    $behind = $learner('Brian Okello', '0772111222', 100000);  // 20% paid
    $ahead = $learner('Grace Namuli', '0772333444', 400000);   // 80% paid

    Livewire::test(ListFeeReminders::class)
        ->mountAction('send')
        ->setActionData(['paid_below' => 50, 'skip_recent' => false])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(FeeReminder::where('channel', 'sms')->pluck('student_id')->all())
        ->toContain($behind->id)
        ->not->toContain($ahead->id);
});

it('filters Student Accounts by percentage paid and by amount paid', function () {
    $class = $this->student->schoolClass;
    $term = Term::current();
    $paidSoFar = function (string $name, float $paid) use ($class, $term): Student {
        $student = Student::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'name' => $name, 'admission_no' => 'A-'.$name, 'status' => 'active']);
        StudentCharge::create(['school_id' => $this->school->id, 'student_id' => $student->id, 'term_id' => $term->id, 'description' => 'Tuition', 'amount' => 500000, 'charged_on' => '2026-09-14']);
        StudentPayment::create(['school_id' => $this->school->id, 'student_id' => $student->id, 'amount' => $paid, 'paid_on' => '2026-09-20', 'method' => 'cash']);

        return $student;
    };
    $behind = $paidSoFar('Brian', 100000);
    $ahead = $paidSoFar('Grace', 400000);

    Livewire::test(ListFeeBalances::class)
        ->filterTable('percentage', ['operator' => 'lt', 'percent' => 50])
        ->assertCanSeeTableRecords([$behind, $this->student])
        ->assertCanNotSeeTableRecords([$ahead]);

    Livewire::test(ListFeeBalances::class)
        ->filterTable('paid_at_least', ['amount' => 200000])
        ->assertCanSeeTableRecords([$ahead])
        ->assertCanNotSeeTableRecords([$behind, $this->student]);
});
