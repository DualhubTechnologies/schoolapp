<?php

use App\Filament\Pages\EnterMarks;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Mark;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('app');

    $school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);
    SubscriptionManager::startTrial($school);
    $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026', 'is_current' => true]);
    $term = Term::create(['school_id' => $school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true]);
    $this->class = SchoolClass::create(['school_id' => $school->id, 'name' => 'P.4']);
    $this->subject = Subject::create(['school_id' => $school->id, 'name' => 'English', 'curriculum' => $this->class->curriculum() ?? 'primary']);
    $this->class->subjects()->attach($this->subject->id, ['is_compulsory' => true]);
    $this->assessment = Assessment::create(['school_id' => $school->id, 'term_id' => $term->id, 'name' => 'End of term', 'type' => 'eot', 'max_score' => 100]);
    $this->student = Student::create(['school_id' => $school->id, 'school_class_id' => $this->class->id, 'name' => 'Aisha Nakato', 'admission_no' => 'ADM-001', 'status' => 'active']);

    $this->actingAs(User::factory()->create(['school_id' => $school->id])->assignRole('School Admin'));
});

function markSheet()
{
    return Livewire::test(EnterMarks::class)
        ->set('assessmentId', test()->assessment->id)
        ->set('classId', test()->class->id)
        ->set('subjectId', test()->subject->id);
}

it('saves marks by itself once they are valid, without a pop-up', function () {
    markSheet()
        ->set("scores.{$this->student->id}", '150')
        ->call('autosave')
        ->assertSet('savedAt', null)
        ->assertHasErrors(["scores.{$this->student->id}"])
        ->set("scores.{$this->student->id}", '80')
        ->call('autosave')
        ->assertHasNoErrors()
        ->assertNotNotified()
        ->assertSet('savedAt', fn ($value) => filled($value));

    expect((float) Mark::where('student_id', $this->student->id)->value('score'))->toBe(80.0);
});

it('still confirms a save made with the button', function () {
    markSheet()
        ->set("scores.{$this->student->id}", '65')
        ->call('save')
        ->assertNotified('Marks saved (1)');
});
