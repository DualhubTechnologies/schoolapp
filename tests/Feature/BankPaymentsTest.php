<?php

use App\Filament\Pages\ReceivePayment;
use App\Filament\Pages\Reports\FeeCollectionsReport;
use App\Models\AcademicYear;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\StudentPayment;
use App\Models\Term;
use App\Models\User;
use App\Services\Banking\BankReconciliation;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-10-20 10:00:00');
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    $term = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true, 'start_date' => '2026-09-14', 'end_date' => '2026-12-04']);
    $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'S.2']);

    $this->fees = BankAccount::create(['school_id' => $this->school->id, 'name' => 'Fees', 'bank' => 'Stanbic Bank', 'account_number' => '9030012345', 'is_active' => true]);

    foreach (['Aisha Nakato' => 'ADM-001', 'Brian Nakato' => 'ADM-002'] as $name => $no) {
        $student = Student::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'name' => $name, 'admission_no' => $no, 'status' => 'active']);
        StudentCharge::create(['school_id' => $this->school->id, 'student_id' => $student->id, 'term_id' => $term->id, 'description' => 'Tuition', 'amount' => 450000, 'charged_on' => '2026-09-14']);
    }

    $this->aisha = Student::where('admission_no', 'ADM-001')->sole();
    $this->brian = Student::where('admission_no', 'ADM-002')->sole();

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
});

function payByBank(Student $student, array $data = []): Testable
{
    return Livewire::withQueryParams(['student' => $student->id])
        ->test(ReceivePayment::class)
        ->fillForm(['amount' => 200000, 'method' => 'bank', 'reference' => 'SLIP-1001', ...$data])
        ->call('save');
}

it('fills in the account itself when the school has only one, and prints it on the receipt', function () {
    payByBank($this->aisha)->assertHasNoFormErrors();

    $payment = StudentPayment::sole();
    expect($payment->bank_account_id)->toBe($this->fees->id);

    $this->get(ReceivePayment::receiptUrl($payment))->assertOk()
        ->assertSee('Paid into')
        ->assertSee('Stanbic Bank · A/C 9030012345');
});

it('asks which account when the school has several', function () {
    $development = BankAccount::create(['school_id' => $this->school->id, 'name' => 'Development', 'bank' => 'Centenary Bank', 'account_number' => '3100099999', 'is_active' => true]);

    payByBank($this->aisha)->assertHasFormErrors(['bank_account_id' => 'required']);
    expect(StudentPayment::count())->toBe(0);

    payByBank($this->aisha, ['bank_account_id' => $development->id])->assertHasNoFormErrors();
    expect(StudentPayment::sole()->bank_account_id)->toBe($development->id);

    // Cash and mobile money do not go into a bank account.
    Livewire::withQueryParams(['student' => $this->brian->id])->test(ReceivePayment::class)
        ->fillForm(['amount' => 50000, 'method' => 'cash'])
        ->call('save')
        ->assertHasNoFormErrors();
    expect(StudentPayment::where('method', 'cash')->sole()->bank_account_id)->toBeNull();
});

it('warns about a slip number already on a receipt, and saves on the second click for brothers and sisters', function () {
    payByBank($this->aisha)->assertHasNoFormErrors();

    $page = payByBank($this->brian, ['reference' => ' slip-1001 '])
        ->assertNotified('Bank slip slip-1001 is already on a receipt');
    expect(StudentPayment::count())->toBe(1);

    $page->call('save')->assertHasNoFormErrors();
    expect(StudentPayment::count())->toBe(2)
        ->and(StudentPayment::where('student_id', $this->brian->id)->sole()->reference)->toBe('slip-1001');
});

it('offers a receipt only on the statement of the account it was paid into', function () {
    $development = BankAccount::create(['school_id' => $this->school->id, 'name' => 'Development', 'bank' => 'Centenary Bank', 'is_active' => true]);
    $intoFees = StudentPayment::create(['school_id' => $this->school->id, 'student_id' => $this->aisha->id, 'amount' => 200000, 'paid_on' => '2026-10-20', 'method' => 'bank', 'reference' => 'SLIP-1', 'bank_account_id' => $this->fees->id]);
    $older = StudentPayment::create(['school_id' => $this->school->id, 'student_id' => $this->brian->id, 'amount' => 200000, 'paid_on' => '2026-10-20', 'method' => 'bank', 'reference' => 'SLIP-2']);

    $line = fn (BankAccount $account) => BankStatementLine::create(['school_id' => $this->school->id, 'bank_account_id' => $account->id, 'fingerprint' => uniqid(), 'line_date' => '2026-10-20', 'description' => 'DEPOSIT', 'money_in' => 200000, 'money_out' => 0, 'status' => 'unmatched']);
    $reconciliation = app(BankReconciliation::class);

    expect($reconciliation->candidates($line($this->fees))->pluck('id')->sort()->values()->all())->toBe([$intoFees->id, $older->id])
        ->and($reconciliation->candidates($line($development))->pluck('id')->all())->toBe([$older->id]);

    expect(fn () => $reconciliation->match($line($development), $intoFees))
        ->toThrow(RuntimeException::class, 'paid into another of the school\'s bank accounts');
});

it('totals fees paid by bank for each account in Fee Collections', function () {
    $development = BankAccount::create(['school_id' => $this->school->id, 'name' => 'Development', 'bank' => 'Centenary Bank', 'is_active' => true]);
    foreach ([[$this->fees, 200000], [$this->fees, 100000], [$development, 50000], [null, 30000]] as $i => [$account, $amount]) {
        StudentPayment::create(['school_id' => $this->school->id, 'student_id' => $this->aisha->id, 'amount' => $amount, 'paid_on' => '2026-10-20', 'method' => 'bank', 'reference' => "S-{$i}", 'bank_account_id' => $account?->id]);
    }

    $rows = Livewire::test(FeeCollectionsReport::class)
        ->assertSee('Paid by bank, by account')
        ->instance()->byBankAccount();

    expect($rows)->toBe([
        ['label' => $this->fees->label(), 'receipts' => 2, 'total' => 300000.0],
        ['label' => $development->label(), 'receipts' => 1, 'total' => 50000.0],
        ['label' => 'Account not recorded', 'receipts' => 1, 'total' => 30000.0],
    ]);
});
