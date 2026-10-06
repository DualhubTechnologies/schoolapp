<?php

use App\Filament\App\Resources\Assessments\AssessmentResource;
use App\Filament\Pages\EnterMarks;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\ClassLevel;
use App\Models\GradingScale;
use App\Models\Mark;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Academics\ResultsCalculator;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Unity High', 'slug' => 'unity', 'email' => 'unity@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    $this->term1 = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 1', 'sequence' => 1]);
    $this->term2 = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 2', 'sequence' => 2, 'is_current' => true]);

    $level = ClassLevel::firstOrCreate(['school_id' => $this->school->id, 'name' => 'O-Level'], ['curriculum' => 'o_level']);
    $level->update(['curriculum' => 'o_level']);
    $this->class = SchoolClass::create(['school_id' => $this->school->id, 'class_level_id' => $level->id, 'name' => 'S.3']);
    $this->agric = Subject::create(['school_id' => $this->school->id, 'name' => 'Agriculture', 'curriculum' => 'o_level']);
    $this->class->subjects()->attach($this->agric->id, ['is_compulsory' => true]);

    $scale = GradingScale::create(['school_id' => $this->school->id, 'curriculum' => 'o_level', 'purpose' => 'subject', 'name' => 'O-Level']);
    foreach (config('academics.grading.o_level.subject') as $i => [$grade, $min, $max, $value, $descriptor]) {
        $scale->bands()->create(['grade' => $grade, 'min_score' => $min, 'max_score' => $max, 'value' => $value, 'descriptor' => $descriptor, 'sort_order' => $i + 1]);
    }

    $this->student = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'name' => 'Joram Mulungi', 'admission_no' => 'U3562/002', 'status' => 'active']);

    // Term 2: AOI 2.4/3 at 20%, end of term 60/100 at 80%, and an
    // Apiculture project 8.5/10 reported on its own.
    $aoi = Assessment::create(['school_id' => $this->school->id, 'term_id' => $this->term2->id, 'name' => 'Activity of Integration', 'type' => 'ca', 'max_score' => 3, 'weight' => 20]);
    $project = Assessment::create(['school_id' => $this->school->id, 'term_id' => $this->term2->id, 'name' => 'Apiculture', 'type' => 'project', 'max_score' => 10, 'weight' => 20]);
    $eot = Assessment::create(['school_id' => $this->school->id, 'term_id' => $this->term2->id, 'name' => 'End of term', 'type' => 'eot', 'max_score' => 100, 'weight' => 80]);
    Mark::create(['assessment_id' => $aoi->id, 'student_id' => $this->student->id, 'subject_id' => $this->agric->id, 'score' => 2.4]);
    Mark::create(['assessment_id' => $project->id, 'student_id' => $this->student->id, 'subject_id' => $this->agric->id, 'score' => 8.5, 'comment' => 'Well kept hives']);
    Mark::create(['assessment_id' => $eot->id, 'student_id' => $this->student->id, 'subject_id' => $this->agric->id, 'score' => 60]);

    // Term 1: one exam, 50%.
    $term1Exam = Assessment::create(['school_id' => $this->school->id, 'term_id' => $this->term1->id, 'name' => 'End of term', 'type' => 'eot', 'max_score' => 100, 'weight' => 100]);
    Mark::create(['assessment_id' => $term1Exam->id, 'student_id' => $this->student->id, 'subject_id' => $this->agric->id, 'score' => 50]);

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
});

it('splits the term score into its formative and exam parts', function () {
    $results = app(ResultsCalculator::class)->forClass($this->class, $this->term2);
    $agric = $results['rows']->first()['subjects'][$this->agric->id];

    expect($results['split'])->toBe(['formative' => 20, 'summative' => 80])
        ->and($agric['formative'])->toBe(16.0)
        ->and($agric['summative'])->toBe(48.0)
        ->and($agric['final'])->toBe(64.0);
});

it('averages an O-Level term\'s Activities of Integration into one 20% share', function () {
    // A second AOI, 3/3 at 20%: the AOIs average (80% + 100%) / 2 = 90%,
    // which counts 20%, not 40%.
    $aoi2 = Assessment::create(['school_id' => $this->school->id, 'term_id' => $this->term2->id, 'name' => 'Activity of Integration 2', 'type' => 'ca', 'max_score' => 3, 'weight' => 20]);
    Mark::create(['assessment_id' => $aoi2->id, 'student_id' => $this->student->id, 'subject_id' => $this->agric->id, 'score' => 3]);

    $results = app(ResultsCalculator::class)->forClass($this->class, $this->term2);
    $agric = $results['rows']->first()['subjects'][$this->agric->id];

    expect($results['split'])->toBe(['formative' => 20, 'summative' => 80])
        ->and($agric['formative'])->toBe(18.0)
        ->and($agric['summative'])->toBe(48.0)
        ->and($agric['final'])->toBe(66.0)
        ->and(Assessment::weightProblems($this->school->id, $this->term2->id, ['o_level' => 'O-Level']))->toBe([]);
});

it('gives O-Level exams the standard weight, whatever is typed', function () {
    $aoi = Assessment::create(['school_id' => $this->school->id, 'term_id' => $this->term2->id, 'curriculum' => 'o_level', 'name' => 'AOI', 'type' => 'ca', 'max_score' => 20, 'weight' => 50]);
    $eot = Assessment::create(['school_id' => $this->school->id, 'term_id' => $this->term2->id, 'curriculum' => 'o_level', 'name' => 'EOT', 'type' => 'eot', 'max_score' => 100, 'weight' => 10]);

    expect((float) $aoi->weight)->toBe(20.0)
        ->and((float) $eot->weight)->toBe(80.0);
});

it('keeps project work out of the term result, whatever weight is typed', function () {
    expect((float) Assessment::where('type', 'project')->sole()->weight)->toBe(0.0);
});

it('prints each CA out of 3, their average, CA /20 and End of Term /80, a project work section and each term\'s average', function () {
    // A second AOI marked out of 20: 20/20. CA1 2.4/3 (80%), CA2 3/3
    // (100%), average 2.7/3 (90%), so CA 18/20.
    $aoi2 = Assessment::create(['school_id' => $this->school->id, 'term_id' => $this->term2->id, 'name' => 'Activity of Integration 2', 'type' => 'ca', 'max_score' => 20, 'weight' => 20]);
    Mark::create(['assessment_id' => $aoi2->id, 'student_id' => $this->student->id, 'subject_id' => $this->agric->id, 'score' => 20]);

    $this->get(route('filament.app.academics.report-cards', ['term' => $this->term2->id, 'class' => $this->class->id]))
        ->assertOk()
        ->assertSeeInOrder(['CA1', 'CA2', 'CA avg', 'CA', '/20', 'End of Term', '/80', 'Total /100'], false)
        ->assertSeeInOrder(['Agriculture', '2.4', '3', '2.7', '18', '48', '66'], false)
        ->assertSee('Project work')
        ->assertSee('Apiculture')
        ->assertSee('8.5 / 10')
        ->assertSee('85%')
        ->assertSee('Well kept hives')
        ->assertSee('Average by term')
        ->assertSeeInOrder(['Term 1', '50%', 'Term 2', '66%']);
});

it('averages a subject\'s papers in each exam and prints each paper', function () {
    $this->agric->update(['papers' => 2]);
    $eot = Assessment::where('term_id', $this->term2->id)->where('type', 'eot')->sole();
    Mark::where('assessment_id', $eot->id)->update(['paper' => 1]);
    Mark::create(['assessment_id' => $eot->id, 'student_id' => $this->student->id, 'subject_id' => $this->agric->id, 'paper' => 2, 'score' => 70]);

    $agric = app(ResultsCalculator::class)->forClass($this->class, $this->term2)['rows']->first()['subjects'][$this->agric->id];

    // Papers 60 and 70 average 65; AOI 80% at 20% + 65 at 80% = 68.
    expect($agric['scores'][$eot->id]['raw'])->toBe(65.0)
        ->and($agric['scores'][$eot->id]['papers'])->toBe([1 => '60.00', 2 => '70.00'])
        ->and($agric['final'])->toBe(68.0);

    $this->get(route('filament.app.academics.report-cards', ['term' => $this->term2->id, 'class' => $this->class->id]))
        ->assertOk()
        ->assertSee('P1 60')
        ->assertSee('P2 70');
});

it('enters marks for each paper on its own', function () {
    $this->agric->update(['papers' => 2]);
    $eot = Assessment::where('term_id', $this->term2->id)->where('type', 'eot')->sole();

    Livewire::test(EnterMarks::class)
        ->set('assessmentId', $eot->id)
        ->set('classId', $this->class->id)
        ->set('subjectId', $this->agric->id)
        ->assertSee('Paper 2')
        ->assertSet("scores.{$this->student->id}", '60')
        ->set('paper', 2)
        ->assertSet("scores.{$this->student->id}", null)
        ->set("scores.{$this->student->id}", '70')
        ->call('save')
        ->assertNotified('Marks saved (1)');

    expect(Mark::where('assessment_id', $eot->id)->orderBy('paper')->pluck('score', 'paper')->all())->toBe([1 => '60.00', 2 => '70.00']);
});

it('suggests O-Level weights of AOIs 20% and end of term 80%, with project work reported on its own', function () {
    $weights = config('academics.default_weights.o_level');

    expect($weights['ca'])->toBe(20)
        ->and($weights['project'])->toBe(0)
        ->and($weights['eot'])->toBe(80)
        ->and(array_sum($weights))->toBe(100);
});

it('offers O-Level only the standard exam types: CA, end of term and project work', function () {
    expect(array_keys(AssessmentResource::typeOptions('o_level')))->toBe(['eot', 'ca', 'project'])
        ->and(array_keys(AssessmentResource::typeOptions('a_level')))->toContain('bot', 'mot', 'eot', 'other')
        ->and(AssessmentResource::typeOptions('a_level'))->not->toHaveKey('topics');

    // An O-Level Mid-Term made before the change keeps its type when edited.
    $midTerm = Assessment::create(['school_id' => $this->school->id, 'term_id' => $this->term2->id, 'curriculum' => 'o_level', 'name' => 'Mid-Term', 'type' => 'mot', 'max_score' => 100, 'weight' => 0]);

    expect(AssessmentResource::typeOptions('o_level', $midTerm))->toHaveKey('mot');
});
