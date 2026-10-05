<?php

use App\Filament\App\Resources\BankStatementLines\Pages\ListBankStatementLines;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\FinanceCategory;
use App\Models\FinanceEntry;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentPayment;
use App\Models\User;
use App\Services\Banking\BankReconciliation;
use App\Services\Banking\BankStatementReader;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();

    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);
    SubscriptionManager::startTrial($this->school);
    FinanceCategory::ensureDefaults($this->school->id);

    $this->account = BankAccount::create(['school_id' => $this->school->id, 'name' => 'School fees account', 'bank' => 'Centenary Bank', 'account_number' => '3100012345']);
    $this->student = Student::create(['school_id' => $this->school->id, 'name' => 'Aisha Nakato', 'admission_no' => 'ADM-001', 'status' => 'active']);

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id, 'name' => 'Bursar Jane'])->assignRole('School Admin'));
});

/** A statement as Centenary's internet banking exports it. */
function centenaryStatement(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'stmt').'.csv';

    file_put_contents($path, implode("\n", [
        'CENTENARY BANK',
        'ACCOUNT STATEMENT',
        'Account Name:,HOPE PRIMARY SCHOOL',
        'Account Number:,3100012345',
        'Period:,01-Oct-2026 to 05-Oct-2026',
        '',
        'Tran Date,Value Date,Tran Particulars,Chq No,Withdrawals,Deposits,Balance',
        '01-10-2026,01-10-2026,OPENING BALANCE,,,,"5,000,000.00"',
        ...$rows,
        ',,TOTAL,,"0.00","0.00",',
    ]));

    return $path;
}

function expenseCategoryId(School $school, string $like = '%'): int
{
    return (int) FinanceCategory::where('school_id', $school->id)->where('type', 'expense')->whereNull('system_key')->where('name', 'like', $like)->value('id');
}

it('reads a Centenary statement: the table under the account details, without balance and total rows', function () {
    $lines = app(BankStatementReader::class)->read(centenaryStatement([
        '02-10-2026,02-10-2026,CASH DEP BY NAKATO SARAH,,,"450,000.00","5,450,000.00"',
        '03-10-2026 10:15:00,03-10-2026,TOTAL ENERGIES FUEL,000123,"120,000.00",,"5,330,000.00"',
    ]));

    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toMatchArray(['date' => '2026-10-02', 'description' => 'CASH DEP BY NAKATO SARAH', 'in' => 450000.0, 'out' => 0.0, 'balance' => 5450000.0])
        ->and($lines[1])->toMatchArray(['date' => '2026-10-03', 'reference' => '000123', 'in' => 0.0, 'out' => 120000.0]);
});

it('refuses a file without statement columns', function () {
    $path = tempnam(sys_get_temp_dir(), 'x').'.csv';
    file_put_contents($path, "Name,Class\nAisha,P.4\n");

    expect(fn () => app(BankStatementReader::class)->read($path))->toThrow(RuntimeException::class, 'column headings');
});

it('imports each line once, even when the same statement is imported again', function () {
    $path = centenaryStatement([
        '02-10-2026,02-10-2026,CASH DEP,,,"50,000.00","5,050,000.00"',
        '02-10-2026,02-10-2026,CASH DEP,,,"50,000.00","5,050,000.00"',
        '03-10-2026,03-10-2026,LEDGER FEE,,"5,500.00",,"5,044,500.00"',
    ]);

    $reconciliation = app(BankReconciliation::class);

    expect($reconciliation->import($this->account, $path))->toBe(['added' => 3, 'already' => 0])
        ->and($reconciliation->import($this->account, $path))->toBe(['added' => 0, 'already' => 3])
        ->and(BankStatementLine::count())->toBe(3);
});

it('matches lines to receipts and vouchers by itself when it is clear', function () {
    $receipt = StudentPayment::create(['school_id' => $this->school->id, 'student_id' => $this->student->id, 'amount' => 450000, 'paid_on' => '2026-10-01', 'method' => 'bank', 'reference' => 'DEP77812']);
    $cheque = FinanceEntry::create(['school_id' => $this->school->id, 'type' => 'expense', 'finance_category_id' => expenseCategoryId($this->school), 'entry_date' => '2026-10-02', 'amount' => 120000, 'description' => 'Fuel', 'method' => 'cheque', 'reference' => '000123']);

    // Two equal cash-paid receipts: cash is not banked one by one, so neither is a candidate.
    StudentPayment::create(['school_id' => $this->school->id, 'student_id' => $this->student->id, 'amount' => 75000, 'paid_on' => '2026-10-02', 'method' => 'cash']);

    // Two bank receipts of the same amount and no reference on the line: left for the bursar.
    foreach (['2026-10-03', '2026-10-04'] as $day) {
        StudentPayment::create(['school_id' => $this->school->id, 'student_id' => $this->student->id, 'amount' => 300000, 'paid_on' => $day, 'method' => 'bank', 'reference' => 'X'.$day]);
    }

    $reconciliation = app(BankReconciliation::class);
    $reconciliation->import($this->account, centenaryStatement([
        '02-10-2026,02-10-2026,DEPOSIT REF DEP77812 NAKATO,,,"450,000.00","5,450,000.00"',
        '03-10-2026,03-10-2026,CHQ PAYMENT,000123,"120,000.00",,"5,330,000.00"',
        '04-10-2026,04-10-2026,CASH DEPOSIT,,,"300,000.00","5,630,000.00"',
        '05-10-2026,05-10-2026,SMS ALERT CHARGES,,"1,500.00",,"5,628,500.00"',
    ]));

    expect($reconciliation->autoMatch($this->account))->toBe(2);

    $lines = BankStatementLine::orderBy('line_date')->get();

    expect($lines[0]->status)->toBe('matched')
        ->and($lines[0]->matchable->is($receipt))->toBeTrue()
        ->and($lines[1]->matchable->is($cheque))->toBeTrue()
        ->and($lines[2]->status)->toBe('unmatched')
        ->and($reconciliation->candidates($lines[2]))->toHaveCount(2)
        ->and($lines[3]->status)->toBe('unmatched');

    // A record matched once is not offered again.
    expect($reconciliation->candidates($lines[0]))->toHaveCount(0);
});

it('records bank-only money as an expense matched to the line, and marks other lines explained', function () {
    $reconciliation = app(BankReconciliation::class);
    $reconciliation->import($this->account, centenaryStatement([
        '05-10-2026,05-10-2026,SMS ALERT CHARGES,,"1,500.00",,"4,998,500.00"',
        '05-10-2026,05-10-2026,SALARY BULK PAYMENT,,"3,200,000.00",,"1,798,500.00"',
    ]));

    [$charges, $salaries] = BankStatementLine::orderBy('id')->get()->all();

    $entry = $reconciliation->recordEntry($charges, expenseCategoryId($this->school), 'SMS alert charges', 'Centenary Bank');

    expect($entry)
        ->type->toBe('expense')
        ->method->toBe('bank')
        ->recorded_by->toBe('Bursar Jane')
        ->and((float) $entry->amount)->toBe(1500.0)
        ->and($entry->entry_date->toDateString())->toBe('2026-10-05')
        ->and($charges->fresh()->matchable->is($entry))->toBeTrue();

    $reconciliation->explain($salaries, 'Salaries (payroll)');
    expect($salaries->fresh())->status->toBe('explained')->note->toBe('Salaries (payroll)');

    $reconciliation->unmatch($salaries->fresh());
    expect($salaries->fresh())->status->toBe('unmatched')->note->toBeNull();

    expect($reconciliation->summary($this->account))->toMatchArray([
        'statement_balance' => 1798500.0,
        'unmatched' => 1,
        'unmatched_out' => 3200000.0,
    ]);
});

it('shows the account\'s statement on the Bank reconciliation page, and matches from there', function () {
    $reconciliation = app(BankReconciliation::class);
    $reconciliation->import($this->account, centenaryStatement([
        '04-10-2026,04-10-2026,CASH DEPOSIT,,,"300,000.00","5,300,000.00"',
    ]));
    $line = BankStatementLine::sole();
    $receipt = StudentPayment::create(['school_id' => $this->school->id, 'student_id' => $this->student->id, 'amount' => 300000, 'paid_on' => '2026-10-04', 'method' => 'bank', 'reference' => 'ABC']);

    Filament::setCurrentPanel('app');

    Livewire::withQueryParams(['account' => $this->account->id])
        ->test(ListBankStatementLines::class)
        ->assertOk()
        ->assertSee(['School fees account', '1 lines to reconcile', 'CASH DEPOSIT'])
        ->assertCanSeeTableRecords([$line])
        ->callAction('autoMatch')
        ->assertNotified();

    expect($line->fresh()->matchable->is($receipt))->toBeTrue();
});

it('keeps bank reconciliation to people with the Finance module', function () {
    Filament::setCurrentPanel('app');
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('Teacher'));

    $this->get(ListBankStatementLines::getUrl())->assertForbidden();
});
