<?php

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\AdmissionNumbers;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->school = School::create(['name' => 'GLOBAL HIGH SCHOOL - OMUNGYENYI', 'slug' => 'ghs', 'email' => 'ghs@example.com', 'school_type' => 'secondary']);
    $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'S.1']);
});

function admit(string $number): Student
{
    return Student::create(['school_id' => test()->school->id, 'school_class_id' => test()->class->id, 'name' => 'Learner '.$number, 'admission_no' => $number, 'status' => 'active']);
}

it('gives school initials, the year and a serial: GHS/2026/0001', function () {
    Carbon::setTestNow('2026-02-03');

    expect(AdmissionNumbers::next($this->school))->toBe('GHS/2026/0001');

    admit('GHS/2026/0001');
    admit('GHS/2026/0002');

    expect(AdmissionNumbers::next($this->school))->toBe('GHS/2026/0003');
});

it('starts the serial again each year', function () {
    Carbon::setTestNow('2026-02-03');
    admit('GHS/2026/0001');
    admit('GHS/2026/0002');

    Carbon::setTestNow('2027-02-01');

    expect(AdmissionNumbers::next($this->school))->toBe('GHS/2027/0001');
});

it('ignores the old plain numbers already given', function () {
    Carbon::setTestNow('2026-02-03');
    admit('001');
    admit('002');

    expect(AdmissionNumbers::next($this->school))->toBe('GHS/2026/0001');
});

it('uses the school\'s chosen initials, starting number and style', function () {
    Carbon::setTestNow('2026-06-01');

    $this->school->update(['admission_no_initials' => 'gh-s', 'admission_no_start' => 348]);
    expect(AdmissionNumbers::next($this->school->fresh()))->toBe('GHS/2026/0348');

    $this->school->update(['admission_no_style' => 'year']);
    expect(AdmissionNumbers::next($this->school->fresh()))->toBe('2026/0348');

    admit('007');
    $this->school->update(['admission_no_style' => 'plain']);
    expect(AdmissionNumbers::next($this->school->fresh()))->toBe('008');
});
