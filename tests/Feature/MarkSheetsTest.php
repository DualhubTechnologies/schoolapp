<?php

use App\Filament\Pages\ClassResults;
use App\Filament\Pages\EnterMarks;
use App\Filament\Pages\MarksProgress;
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
use App\Services\Academics\MarkSheets;
use App\Services\Academics\ResultsCalculator;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    $this->term = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true]);
    $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'P.4']);
    $this->subject = Subject::create(['school_id' => $this->school->id, 'name' => 'English', 'curriculum' => 'primary']);

    $this->teacherUser = User::factory()->create(['school_id' => $this->school->id])->assignRole('Teacher');
    $this->teacher = Staff::create(['school_id' => $this->school->id, 'user_id' => $this->teacherUser->id, 'name' => 'Okello James', 'staff_no' => 'ST-1', 'position' => 'Teacher', 'employment_date' => '2020-01-06', 'status' => 'active']);
    $this->class->subjects()->attach($this->subject->id, ['is_compulsory' => true, 'teacher_id' => $this->teacher->id]);

    $this->mot = Assessment::create(['school_id' => $this->school->id, 'term_id' => $this->term->id, 'name' => 'Mid-Term', 'type' => 'mot', 'max_score' => 50, 'weight' => 40]);
    $this->eot = Assessment::create(['school_id' => $this->school->id, 'term_id' => $this->term->id, 'name' => 'End of Term', 'type' => 'eot', 'max_score' => 100, 'weight' => 60]);

    $this->aisha = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'name' => 'Aisha Nakato', 'admission_no' => 'ADM-001', 'status' => 'active']);
    $this->brian = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'name' => 'Brian Ssemanda', 'admission_no' => 'ADM-002', 'status' => 'active']);

    $this->admin = User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin');
});

function openSheet(Assessment $assessment)
{
    return Livewire::test(EnterMarks::class)
        ->set('assessmentId', $assessment->id)
        ->set('classId', test()->class->id)
        ->set('subjectId', test()->subject->id);
}

it('lets a teacher submit a sheet, after which only the Director of Studies can change it', function () {
    $this->actingAs($this->teacherUser);

    openSheet($this->eot)
        ->set("scores.{$this->aisha->id}", '70')
        ->set("absent.{$this->brian->id}", true)
        ->callAction('submitSheet')
        ->assertNotified('Mark sheet submitted');

    $sheet = MarkSheet::where('assessment_id', $this->eot->id)->sole();
    expect($sheet->status)->toBe('submitted')
        ->and($sheet->submitted_by)->toBe($this->teacherUser->id)
        ->and((float) Mark::where('student_id', $this->aisha->id)->value('score'))->toBe(70.0);

    // The teacher can no longer change it...
    openSheet($this->eot)
        ->assertSet("scores.{$this->aisha->id}", '70')
        ->set("scores.{$this->aisha->id}", '90')
        ->call('save')
        ->assertNotified('This mark sheet has been submitted');

    expect((float) Mark::where('student_id', $this->aisha->id)->value('score'))->toBe(70.0);

    // ...but the Director of Studies can, and approves it.
    $this->actingAs($this->admin);

    openSheet($this->eot)
        ->set("scores.{$this->aisha->id}", '72')
        ->callAction('approveSheet')
        ->assertNotified('Mark sheet approved');

    expect($sheet->fresh()->status)->toBe('approved')
        ->and((float) Mark::where('student_id', $this->aisha->id)->value('score'))->toBe(72.0);

    // Approved sheets are closed to everyone until reopened.
    openSheet($this->eot)
        ->set("scores.{$this->aisha->id}", '10')
        ->call('save')
        ->assertNotified('This mark sheet has been approved');
});

it('returns a sheet to the teacher with a note of what to correct', function () {
    MarkSheet::for($this->eot, $this->class->id, $this->subject->id)->submit($this->teacherUser);

    $this->actingAs($this->admin);
    openSheet($this->eot)
        ->callAction('returnSheet', ['note' => 'Check Brian\'s score'])
        ->assertNotified('Mark sheet reopened for the teacher');

    $sheet = MarkSheet::where('assessment_id', $this->eot->id)->sole();
    expect($sheet->status)->toBe('open')->and($sheet->returned_note)->toBe('Check Brian\'s score');

    $this->actingAs($this->teacherUser);
    openSheet($this->eot)
        ->assertSee('Returned for correction')
        ->assertSee('Check Brian')
        ->set("scores.{$this->brian->id}", '55')
        ->call('save')
        ->assertNotified('Marks saved (1)');
});

it('does not let a teacher approve their own sheet', function () {
    $this->actingAs($this->teacherUser);

    openSheet($this->eot)->assertActionHidden('approveSheet');
});

it('fills and saves a sheet from an uploaded spreadsheet, matching admission numbers', function () {
    $this->actingAs($this->teacherUser);

    $csv = "\xEF\xBB\xBFAdm. No.,Name,Score (out of 100),Comment\n"
        ."adm-001,Aisha Nakato,81,Good work\n"
        ."ADM-002,Brian Ssemanda,AB,\n"
        ."ADM-999,Nobody,50,\n";

    openSheet($this->eot)
        ->call('importRows', $csv)
        ->assertNotified('Marks saved (2)');

    expect((float) Mark::where('student_id', $this->aisha->id)->value('score'))->toBe(81.0)
        ->and(Mark::where('student_id', $this->aisha->id)->value('comment'))->toBe('Good work')
        ->and((bool) Mark::where('student_id', $this->brian->id)->value('is_absent'))->toBeTrue();
});

it('refuses an uploaded score above the exam maximum', function () {
    $this->actingAs($this->teacherUser);

    openSheet($this->mot)
        ->call('importRows', "Adm. No.,Name,Score\nADM-001,Aisha,75\n")
        ->assertHasErrors(["scores.{$this->aisha->id}"]);

    expect(Mark::count())->toBe(0);
});

it('downloads the sheet as a spreadsheet', function () {
    $this->actingAs($this->teacherUser);

    openSheet($this->eot)
        ->set("scores.{$this->aisha->id}", '64')
        ->call('downloadSheet')
        ->assertFileDownloaded('p4-english-end-of-term.csv');
});

it('prints a mark sheet for its teacher but not for another teacher', function () {
    Mark::create(['assessment_id' => $this->eot->id, 'student_id' => $this->aisha->id, 'subject_id' => $this->subject->id, 'score' => 77]);
    $params = ['assessment' => $this->eot->id, 'class' => $this->class->id, 'subject' => $this->subject->id];

    $this->actingAs($this->teacherUser)
        ->get(route('filament.app.academics.mark-sheet', $params))
        ->assertOk()
        ->assertSee('Aisha Nakato')
        ->assertSee('77');

    $this->get(route('filament.app.academics.mark-sheet', $params + ['blank' => 1]))
        ->assertOk()
        ->assertSee('Blank sheet')
        ->assertDontSee('77');

    $other = User::factory()->create(['school_id' => $this->school->id])->assignRole('Teacher');
    Staff::create(['school_id' => $this->school->id, 'user_id' => $other->id, 'name' => 'Other Teacher', 'staff_no' => 'ST-2', 'position' => 'Teacher', 'employment_date' => '2020-01-06', 'status' => 'active']);

    $this->actingAs($other)->get(route('filament.app.academics.mark-sheet', $params))->assertForbidden();
});

it('shows the Director of Studies how far every sheet has got', function () {
    $maths = Subject::create(['school_id' => $this->school->id, 'name' => 'Mathematics', 'curriculum' => 'primary']);
    $this->class->subjects()->attach($maths->id, ['is_compulsory' => true]);
    Mark::create(['assessment_id' => $this->eot->id, 'student_id' => $this->aisha->id, 'subject_id' => $this->subject->id, 'score' => 60]);

    $rows = app(MarkSheets::class)->progress($this->eot)->keyBy(fn ($row) => $row->subject->name);

    expect($rows['English']->status)->toBe('in_progress')
        ->and($rows['English']->entered)->toBe(1)
        ->and($rows['English']->learners)->toBe(2)
        ->and($rows['English']->teacher)->toBe('Okello James')
        ->and($rows['Mathematics']->status)->toBe('not_started');

    MarkSheet::for($this->eot, $this->class->id, $this->subject->id)->submit($this->teacherUser);

    $this->actingAs($this->admin);
    Livewire::test(MarksProgress::class)
        ->set('assessmentId', $this->eot->id)
        ->assertSee('Okello James')
        ->callAction('approveAll')
        ->assertNotified('1 mark sheet approved');

    expect(MarkSheet::where('status', 'approved')->count())->toBe(1);
});

it('keeps the progress board from teachers', function () {
    $this->actingAs($this->teacherUser)->get(MarksProgress::getUrl())->assertForbidden();
});

it('works out results from one exam alone for a mid-term report', function () {
    Mark::create(['assessment_id' => $this->mot->id, 'student_id' => $this->aisha->id, 'subject_id' => $this->subject->id, 'score' => 40]);  // 80%
    Mark::create(['assessment_id' => $this->eot->id, 'student_id' => $this->aisha->id, 'subject_id' => $this->subject->id, 'score' => 50]);  // 50%

    $term = app(ResultsCalculator::class)->forClass($this->class, $this->term);
    $midTerm = app(ResultsCalculator::class)->forClass($this->class, $this->term, null, $this->mot->id);

    $finalFor = fn (array $results) => $results['rows']->firstWhere('student.id', $this->aisha->id)['subjects'][$this->subject->id]['final'];

    expect($finalFor($term))->toBe(62.0)          // 80 × 40% + 50 × 60%
        ->and($finalFor($midTerm))->toBe(80.0)
        ->and($midTerm['exam']->is($this->mot))->toBeTrue()
        ->and($midTerm['assessments'])->toHaveCount(1);

    $this->actingAs($this->admin);
    Livewire::test(ClassResults::class)
        ->set('classId', $this->class->id)
        ->set('examId', $this->mot->id)
        ->assertSee('Mid-Term only');

    $this->get(route('filament.app.academics.report-cards', ['term' => $this->term->id, 'class' => $this->class->id, 'exam' => $this->mot->id]))
        ->assertOk()
        ->assertSee('Term 3')
        ->assertSee('— Mid-Term', false);
});

it('warns when a term\'s exam weights do not add up to 100%', function () {
    expect(Assessment::weightProblems($this->school->id, $this->term->id, ['primary' => 'Primary']))->toBe([]);

    $this->eot->update(['weight' => 50]);

    expect(Assessment::weightProblems($this->school->id, $this->term->id, ['primary' => 'Primary']))->toBe(['Primary' => 90.0]);
});
