<?php

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

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('app');

    $this->school = School::create(['name' => 'Hope Primary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'primary']);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    $this->term = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true]);

    $level = ClassLevel::firstOrCreate(['school_id' => $this->school->id, 'name' => 'Primary'], ['curriculum' => 'primary']);
    $level->update(['curriculum' => 'primary']);
    $this->class = SchoolClass::create(['school_id' => $this->school->id, 'class_level_id' => $level->id, 'name' => 'P.6']);

    $scale = GradingScale::create(['school_id' => $this->school->id, 'curriculum' => 'primary', 'purpose' => 'subject', 'name' => 'Primary']);
    foreach (config('academics.grading.primary.subject') as $i => [$grade, $min, $max, $value, $descriptor]) {
        $scale->bands()->create(['grade' => $grade, 'min_score' => $min, 'max_score' => $max, 'value' => $value, 'descriptor' => $descriptor, 'sort_order' => $i + 1]);
    }

    $this->subjects = [];
    foreach (['English', 'Mathematics', 'Integrated Science', 'Social Studies'] as $name) {
        $this->subjects[$name] = Subject::create(['school_id' => $this->school->id, 'name' => $name, 'curriculum' => 'primary', 'category' => 'core']);
    }
    $this->subjects['Religious Education'] = Subject::create(['school_id' => $this->school->id, 'name' => 'Religious Education', 'curriculum' => 'primary']);
    $this->class->subjects()->attach(collect($this->subjects)->pluck('id')->all(), ['is_compulsory' => true]);

    $this->student = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'name' => 'Sarah Nakato', 'admission_no' => 'P6/001', 'status' => 'active']);

    $this->mot = Assessment::create(['school_id' => $this->school->id, 'term_id' => $this->term->id, 'name' => 'Mid-Term', 'type' => 'mot', 'max_score' => 100, 'weight' => 40, 'sort_order' => 1]);
    $this->eot = Assessment::create(['school_id' => $this->school->id, 'term_id' => $this->term->id, 'name' => 'End of Term', 'type' => 'eot', 'max_score' => 100, 'weight' => 60, 'sort_order' => 2]);

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
});

/**
 * @param  array<string, float>  $scores  subject name => mark
 */
function primaryMarks(Assessment $exam, array $scores): void
{
    foreach ($scores as $subject => $score) {
        Mark::create(['assessment_id' => $exam->id, 'student_id' => test()->student->id, 'subject_id' => test()->subjects[$subject]->id, 'score' => $score]);
    }
}

it('prints each exam on its own with its grades, aggregate and division', function () {
    // Mid-Term: D1 + D2 + C3 + C5 = 11, Division 1. End of Term: D1 + D1 + D2 + D2 = 6.
    primaryMarks($this->mot, ['English' => 85, 'Mathematics' => 72, 'Integrated Science' => 66, 'Social Studies' => 58, 'Religious Education' => 40]);
    primaryMarks($this->eot, ['English' => 90, 'Mathematics' => 81, 'Integrated Science' => 70, 'Social Studies' => 75, 'Religious Education' => 50]);

    $this->get(route('filament.app.academics.report-cards', ['term' => $this->term->id, 'class' => $this->class->id]))
        ->assertOk()
        ->assertSeeInOrder(['Mid-Term', 'End of Term', 'Marks', 'Grade', 'Marks', 'Grade'])
        ->assertSeeInOrder(['English', '85', 'D1', '90', 'D1'])
        ->assertSeeInOrder(['Mathematics', '72', 'D2', '81', 'D1'])
        ->assertSeeInOrder(['Aggregate', '11', '6', 'Division', 'Division 1', 'Division 1'])
        ->assertSeeInOrder(['Result from', 'End of Term', 'Aggregate (4 core subjects)', '6'])
        ->assertDontSee('Term %');
});

it('grades an aggregate of 34 as ungraded, as at PLE', function () {
    // F9 + F9 + P8 + P8 = 34.
    primaryMarks($this->eot, ['English' => 30, 'Mathematics' => 30, 'Integrated Science' => 42, 'Social Studies' => 42]);

    $row = app(ResultsCalculator::class)->forClass($this->class, $this->term, null, $this->eot->id)['rows']->first();

    expect($row['aggregate'])->toBe(34)->and($row['division'])->toBe('Ungraded');
});

it('counts the four lower primary learning areas as core subjects', function () {
    $core = collect(config('academics.subjects.primary'))
        ->filter(fn (array $subject): bool => ($subject['category'] ?? null) === 'core' && in_array(1, $subject['offered_in'] ?? [], true))
        ->pluck('name')
        ->all();

    expect($core)->toBe(['Literacy I', 'Literacy II', 'Numeracy', 'Oral English']);
});
