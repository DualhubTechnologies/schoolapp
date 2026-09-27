<?php

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\StudentPayment;
use App\Models\Term;
use App\Models\TransportRoute;
use App\Services\Transport\TransportLedger;

beforeEach(function () {
    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com']);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'start_date' => '2026-02-01', 'end_date' => '2026-12-10']);
    $this->term2 = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 2', 'sequence' => 2]);
    $this->term3 = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true]);
    $this->route = TransportRoute::create(['school_id' => $this->school->id, 'name' => 'Kakiri', 'fare' => 15000]);
    $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'P.4']);

    $this->student = Student::create([
        'school_id' => $this->school->id, 'school_class_id' => $class->id, 'name' => 'Aisha Nakato',
        'first_name' => 'Aisha', 'last_name' => 'Nakato', 'admission_no' => 'ADM-1', 'gender' => 'female', 'status' => 'active',
    ]);
});

function charge(Term $term, float $amount, bool $transport = false): void
{
    StudentCharge::create([
        'school_id' => test()->school->id, 'student_id' => test()->student->id, 'term_id' => $term->id,
        'transport_route_id' => $transport ? test()->route->id : null,
        'description' => $transport ? 'Transport — Kakiri' : 'Tuition', 'amount' => $amount, 'discount_amount' => 0,
        'charged_on' => '2026-09-15',
    ]);
}

function pay(Term $term, float $amount, string $on = '2026-09-20'): StudentPayment
{
    return StudentPayment::create([
        'school_id' => test()->school->id, 'student_id' => test()->student->id, 'term_id' => $term->id,
        'amount' => $amount, 'paid_on' => $on, 'method' => 'cash',
    ]);
}

function ledger(): array
{
    return (new TransportLedger)->forStudent(test()->student);
}

it('puts each payment towards transport before school fees', function () {
    charge($this->term3, 200000);
    charge($this->term3, 15000, transport: true);

    $first = pay($this->term3, 10000);
    $second = pay($this->term3, 100000, '2026-09-25');

    $l = ledger();

    expect($l['payments'][$first->id])->toBe(10000.0)
        ->and($l['payments'][$second->id])->toBe(5000.0)
        ->and($l['paid'])->toBe(15000.0)
        ->and($l['owed'])->toBe(0.0)
        ->and($l['terms'][$this->term3->id])->toBe(['charged' => 15000.0, 'paid' => 15000.0]);
});

it('clears older transport arrears first', function () {
    charge($this->term2, 15000, transport: true);
    charge($this->term3, 15000, transport: true);

    pay($this->term3, 20000);

    $l = ledger();

    expect($l['terms'][$this->term2->id]['paid'])->toBe(15000.0)
        ->and($l['terms'][$this->term3->id]['paid'])->toBe(5000.0)
        ->and($l['owed'])->toBe(10000.0);
});

it('does not let an earlier term\'s payment pay a later term\'s van unless it is spare money', function () {
    charge($this->term2, 200000);
    charge($this->term3, 200000);
    charge($this->term3, 15000, transport: true);

    $term2Payment = pay($this->term2, 200000, '2026-06-01');

    expect(ledger()['payments'][$term2Payment->id])->toBe(0.0)
        ->and(ledger()['owed'])->toBe(15000.0);
});

it('uses credit from paying ahead to clear the van', function () {
    charge($this->term2, 200000);
    pay($this->term2, 230000, '2026-06-01');   // 30,000 ahead
    charge($this->term3, 15000, transport: true);

    $l = ledger();

    expect($l['paid'])->toBe(15000.0)
        ->and($l['owed'])->toBe(0.0);
});

it('ignores voided payments', function () {
    charge($this->term3, 15000, transport: true);
    $payment = pay($this->term3, 15000);
    $payment->void('Cheque bounced');

    expect(ledger()['paid'])->toBe(0.0)
        ->and(ledger()['owed'])->toBe(15000.0);
});

it('has nothing to split for a learner brought by a parent', function () {
    charge($this->term3, 200000);
    $payment = pay($this->term3, 50000);

    expect(ledger()['payments'][$payment->id])->toBe(0.0)
        ->and(ledger()['charged'])->toBe(0.0);
});
