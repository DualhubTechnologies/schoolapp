<?php

use App\Filament\Pages\EnterMarks;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Mark;
use App\Models\MarkSheet;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Academics\MarksCompleteness;
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
        ->set('allExams', false)
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

it('enters every exam of the term on one sheet, with AB for absent', function () {
    $ca = Assessment::create(['school_id' => $this->assessment->school_id, 'term_id' => $this->assessment->term_id, 'name' => 'Test 1', 'type' => 'ca', 'max_score' => 20]);

    markSheet()
        ->set('allExams', true)
        ->assertSee('All exams this term')
        ->set("grid.{$ca->id}.{$this->student->id}", '25')
        ->call('save')
        ->assertHasErrors(["grid.{$ca->id}.{$this->student->id}"])
        ->set("grid.{$ca->id}.{$this->student->id}", '15')
        ->set("grid.{$this->assessment->id}.{$this->student->id}", 'AB')
        ->call('save')
        ->assertHasNoErrors()
        ->assertNotified('Marks saved (2)');

    expect((float) Mark::where('assessment_id', $ca->id)->value('score'))->toBe(15.0)
        ->and(Mark::where('assessment_id', $this->assessment->id)->value('is_absent'))->toBeTruthy();
});

it('lists nobody on an elective until learners are ticked, then only them', function () {
    $french = Subject::create(['school_id' => $this->assessment->school_id, 'name' => 'French', 'curriculum' => 'primary']);
    $this->class->subjects()->attach($french->id, ['is_compulsory' => false]);
    $other = Student::create(['school_id' => $this->assessment->school_id, 'school_class_id' => $this->class->id, 'name' => 'Brian Okello', 'admission_no' => 'ADM-002', 'status' => 'active']);

    $page = Livewire::test(EnterMarks::class)
        ->set('allExams', false)
        ->set('assessmentId', $this->assessment->id)
        ->set('classId', $this->class->id)
        ->set('subjectId', $french->id)
        ->assertSee('no learners in P.4 have been chosen for it yet')
        ->callAction('chooseLearners', data: ['students' => [$other->id]])
        ->assertHasNoActionErrors();

    expect($page->instance()->sheetStudents()['students']->pluck('id')->all())->toBe([$other->id])
        ->and($other->electives()->pluck('subjects.id')->all())->toBe([$french->id]);
});

it('lets the admin approve every exam of the term from the all-exams sheet', function () {
    $ca = Assessment::create(['school_id' => $this->assessment->school_id, 'term_id' => $this->assessment->term_id, 'name' => 'Test 1', 'type' => 'ca', 'max_score' => 20]);

    markSheet()
        ->set('allExams', true)
        ->set("grid.{$ca->id}.{$this->student->id}", '15')
        ->callAction('approveAll')
        ->assertNotified('2 mark sheets approved');

    expect(MarkSheet::where('school_class_id', $this->class->id)->where('status', 'approved')->count())->toBe(2)
        ->and((float) Mark::where('assessment_id', $ca->id)->value('score'))->toBe(15.0);
});

it('adds a CA for just this class and subject from the mark sheet', function () {
    $maths = Subject::create(['school_id' => $this->assessment->school_id, 'name' => 'Mathematics', 'curriculum' => 'primary']);
    $this->class->subjects()->attach($maths->id, ['is_compulsory' => true]);
    $p5 = SchoolClass::create(['school_id' => $this->assessment->school_id, 'name' => 'P.5']);

    Livewire::test(EnterMarks::class)
        ->set('assessmentId', $this->assessment->id)
        ->set('classId', $this->class->id)
        ->set('subjectId', $this->subject->id)
        ->callAction('addCa', data: ['name' => 'CA 1 — English', 'max_score' => 100])
        ->assertHasNoActionErrors()
        ->assertSet('allExams', true);

    $ca = Assessment::where('type', 'ca')->sole();

    // The new CA comes before End of Term, even when both had order 0.
    expect(Assessment::where('term_id', $this->assessment->term_id)->orderBy('sort_order')->orderBy('id')->pluck('type')->all())->toBe(['ca', 'eot']);

    expect($ca->class_ids)->toBe([$this->class->id])
        ->and($ca->subject_ids)->toBe([$this->subject->id])
        ->and($ca->covers($this->class->fresh(), $this->subject->id))->toBeTrue()
        ->and($ca->covers($this->class->fresh(), $maths->id))->toBeFalse()
        ->and($ca->coversClass($p5->id))->toBeFalse();

    // English's sheet has the CA beside End of Term; Mathematics' does not.
    $english = Livewire::test(EnterMarks::class)
        ->set('assessmentId', $this->assessment->id)
        ->set('classId', $this->class->id)
        ->set('subjectId', $this->subject->id);
    expect($english->instance()->termAssessments()->pluck('id')->all())->toContain($ca->id);

    $mathsSheet = Livewire::test(EnterMarks::class)
        ->set('assessmentId', $this->assessment->id)
        ->set('classId', $this->class->id)
        ->set('subjectId', $maths->id);
    expect($mathsSheet->instance()->termAssessments()->pluck('id')->all())->not->toContain($ca->id);

    // Choosing the CA itself offers only its class and subject.
    $caSheet = Livewire::test(EnterMarks::class)
        ->set('allExams', false)
        ->set('assessmentId', $ca->id);
    expect($caSheet->instance()->classOptions()->keys()->all())->toBe([$this->class->id]);
    $caSheet->set('classId', $this->class->id);
    expect($caSheet->instance()->subjectOptions()->keys()->all())->toBe([$this->subject->id]);
});

it('does not count a CA set for one subject as missing in the others', function () {
    $maths = Subject::create(['school_id' => $this->assessment->school_id, 'name' => 'Mathematics', 'curriculum' => 'primary']);
    $this->class->subjects()->attach($maths->id, ['is_compulsory' => true]);
    $ca = Assessment::create(['school_id' => $this->assessment->school_id, 'term_id' => $this->assessment->term_id, 'name' => 'English CA', 'type' => 'ca', 'max_score' => 100, 'class_ids' => [$this->class->id], 'subject_ids' => [$this->subject->id]]);

    Mark::create(['assessment_id' => $this->assessment->id, 'student_id' => $this->student->id, 'subject_id' => $this->subject->id, 'score' => 70]);
    Mark::create(['assessment_id' => $ca->id, 'student_id' => $this->student->id, 'subject_id' => $this->subject->id, 'score' => 60]);
    Mark::create(['assessment_id' => $this->assessment->id, 'student_id' => $this->student->id, 'subject_id' => $maths->id, 'score' => 50]);

    $missing = app(MarksCompleteness::class)->missingFor($this->assessment->term);

    expect(collect($missing)->flatMap(fn ($c) => $c['missing'])->all())->not->toContain('Mathematics — English CA');
});

it('lists for a teacher only the exams set in a subject they mark', function () {
    $schoolId = $this->class->school_id;
    $science = Subject::create(['school_id' => $schoolId, 'name' => 'Science', 'curriculum' => $this->class->curriculum() ?? 'primary']);
    $this->class->subjects()->attach($science->id, ['is_compulsory' => true]);
    $scienceTest = Assessment::create(['school_id' => $schoolId, 'term_id' => $this->assessment->term_id, 'name' => 'Science test', 'type' => 'ca', 'max_score' => 20, 'subject_ids' => [$science->id]]);

    $user = User::factory()->create(['school_id' => $schoolId])->assignRole('Teacher');
    $staff = Staff::create(['school_id' => $schoolId, 'user_id' => $user->id, 'name' => 'Okello James', 'staff_no' => 'T001', 'position' => 'Teacher', 'employment_date' => '2020-01-06', 'status' => 'active']);
    $this->class->subjects()->updateExistingPivot($this->subject->id, ['teacher_id' => $staff->id]);

    $this->actingAs($user);

    expect(Livewire::test(EnterMarks::class)->instance()->assessmentOptions()->keys()->all())
        ->toContain($this->assessment->id)
        ->not->toContain($scienceTest->id);
});

it('tells apart two exams with the same name', function () {
    $termId = $this->assessment->term_id;
    $schoolId = $this->class->school_id;
    $first = Assessment::create(['school_id' => $schoolId, 'term_id' => $termId, 'name' => 'Continuous Assessment', 'type' => 'ca', 'max_score' => 3, 'held_on' => '2026-09-14']);
    $second = Assessment::create(['school_id' => $schoolId, 'term_id' => $termId, 'name' => 'Continuous Assessment', 'type' => 'ca', 'max_score' => 3, 'held_on' => '2026-10-05']);

    $labels = Livewire::test(EnterMarks::class)->instance()->assessmentOptions();

    expect($labels[$first->id])->toContain('held 14 Sep')
        ->and($labels[$second->id])->toContain('held 5 Oct')
        ->and($labels[$this->assessment->id])->not->toContain('held');
});
