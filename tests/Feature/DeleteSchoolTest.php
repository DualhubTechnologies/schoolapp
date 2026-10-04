<?php

use App\Models\FinanceCategory;
use App\Models\FinanceEntry;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);

    $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'P.4']);
    Student::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'name' => 'Aisha Nakato', 'admission_no' => 'ADM-001', 'status' => 'active']);

    FinanceCategory::ensureDefaults($this->school->id);
    FinanceEntry::create([
        'school_id' => $this->school->id,
        'type' => 'expense',
        'finance_category_id' => FinanceCategory::where('school_id', $this->school->id)->where('type', 'expense')->value('id'),
        'entry_date' => now(),
        'amount' => 50000,
        'description' => 'Chalk',
    ]);

    $this->other = School::create(['name' => 'Other', 'slug' => 'other', 'email' => 'other@example.com', 'school_type' => 'primary']);
    Student::create(['school_id' => $this->other->id, 'name' => 'Aisha Other', 'admission_no' => 'ADM-001', 'status' => 'active']);
});

// Filament's delete and bulk delete both call School::delete().
it('deletes a school that has students in classes and finance entries', function () {
    expect($this->school->delete())->toBeTrue();

    expect(School::find($this->school->id))->toBeNull()
        ->and(Student::where('school_id', $this->school->id)->count())->toBe(0)
        ->and(FinanceEntry::where('school_id', $this->school->id)->count())->toBe(0)
        ->and(FinanceCategory::where('school_id', $this->school->id)->count())->toBe(0)
        ->and(SchoolClass::where('school_id', $this->school->id)->count())->toBe(0)
        ->and(Student::where('school_id', $this->other->id)->count())->toBe(1);
});
