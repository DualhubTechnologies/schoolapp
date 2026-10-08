<?php

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\ClassLevel;
use App\Models\Combination;
use App\Models\GradingScale;
use App\Models\Mark;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Academics\ResultsCalculator;
use App\Services\Academics\UacePrincipalGrade;
use App\Services\Subscriptions\SubscriptionManager;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->school = School::create(['name' => 'Unity High', 'slug' => 'unity', 'email' => 'unity@example.com', 'school_type' => 'secondary']);
    SubscriptionManager::startTrial($this->school);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    $this->term = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true]);
    $level = ClassLevel::firstOrCreate(['school_id' => $this->school->id, 'name' => 'A-Level'], ['curriculum' => 'a_level']);
    $level->update(['curriculum' => 'a_level']);
    $this->class = SchoolClass::create(['school_id' => $this->school->id, 'class_level_id' => $level->id, 'name' => 'S.5']);

    $scale = GradingScale::create(['school_id' => $this->school->id, 'curriculum' => 'a_level', 'purpose' => 'paper', 'name' => 'Paper grades']);
    foreach (config('academics.grading.a_level.paper') as $i => [$grade, $min, $max, $value, $descriptor]) {
        $scale->bands()->create(['grade' => $grade, 'min_score' => $min, 'max_score' => $max, 'value' => $value, 'descriptor' => $descriptor, 'sort_order' => $i + 1]);
    }

    $this->subjects = [];
    foreach (['Mathematics' => 'principal', 'Physics' => 'principal', 'Chemistry' => 'principal', 'General Paper' => 'subsidiary', 'Subsidiary ICT' => 'subsidiary'] as $name => $category) {
        $this->subjects[$name] = Subject::create(['school_id' => $this->school->id, 'name' => $name, 'curriculum' => 'a_level', 'category' => $category]);
    }
    $this->class->subjects()->attach(collect($this->subjects)->pluck('id')->all(), ['is_compulsory' => true]);

    $pcm = Combination::create(['school_id' => $this->school->id, 'name' => 'PCM', 'subsidiary_subject_id' => $this->subjects['Subsidiary ICT']->id]);
    $pcm->subjects()->attach([$this->subjects['Physics']->id, $this->subjects['Chemistry']->id, $this->subjects['Mathematics']->id]);

    $this->student = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'name' => 'Brian Tumusiime', 'admission_no' => '002', 'status' => 'active', 'combination_id' => $pcm->id]);
    $this->exam = Assessment::create(['school_id' => $this->school->id, 'term_id' => $this->term->id, 'curriculum' => 'a_level', 'name' => 'End of Term', 'type' => 'eot', 'max_score' => 100, 'weight' => 50]);

    $this->actingAs(User::factory()->create(['school_id' => $this->school->id])->assignRole('School Admin'));
});

function uaceRow(): array
{
    return app(ResultsCalculator::class)->forClass(test()->class, test()->term)['rows']->first();
}

it('grades A-Level as UNEB does: paper grades, then principal grades and points', function () {
    foreach (['Mathematics' => 85.5, 'Chemistry' => 74.6, 'Physics' => 66.5, 'General Paper' => 60, 'Subsidiary ICT' => 72.5] as $subject => $score) {
        Mark::create(['assessment_id' => $this->exam->id, 'student_id' => $this->student->id, 'subject_id' => $this->subjects[$subject]->id, 'score' => $score]);
    }

    $row = uaceRow();
    $grade = fn (string $subject) => $row['subjects'][$this->subjects[$subject]->id];

    // 85.5 is D1 -> A; 74.6 is C4 -> C; 66.5 is C5 -> D; GP 60 is C6, a pass; Sub-ICT C4, a pass.
    expect($grade('Mathematics')['grade'])->toBe('A')
        ->and($grade('Mathematics')['paper_grades'])->toBe([1 => 'D1'])
        ->and($grade('Chemistry')['grade'])->toBe('C')
        ->and($grade('Physics')['grade'])->toBe('D')
        ->and($grade('General Paper')['grade'])->toBe('C6')
        ->and($grade('General Paper')['value'])->toBe(1.0)
        ->and($row['points'])->toBe(15)
        ->and($row['result_code'])->toBe('ACD/11');
});

it('grades a principal subject sat as two papers from both paper grades', function () {
    $this->subjects['Physics']->update(['papers' => 2]);
    Mark::create(['assessment_id' => $this->exam->id, 'student_id' => $this->student->id, 'subject_id' => $this->subjects['Physics']->id, 'paper' => 1, 'score' => 82]);
    Mark::create(['assessment_id' => $this->exam->id, 'student_id' => $this->student->id, 'subject_id' => $this->subjects['Physics']->id, 'paper' => 2, 'score' => 76]);

    $physics = uaceRow()['subjects'][$this->subjects['Physics']->id];

    // D2 and C3: B.
    expect($physics['paper_grades'])->toBe([1 => 'D2', 2 => 'C3'])
        ->and($physics['grade'])->toBe('B')
        ->and($physics['value'])->toBe(5.0);
});

it('fails a subsidiary below C6', function () {
    Mark::create(['assessment_id' => $this->exam->id, 'student_id' => $this->student->id, 'subject_id' => $this->subjects['General Paper']->id, 'score' => 55]);

    $gp = uaceRow()['subjects'][$this->subjects['General Paper']->id];

    expect($gp['grade'])->toBe('P7')->and($gp['value'])->toBe(0.0);
});

it('follows every example in UNEB\'s award rules', function (array $papers, string $expected) {
    expect(UacePrincipalGrade::fromPapers($papers))->toBe($expected);
})->with([
    [[1, 2], 'A'], [[3, 3], 'B'], [[2, 4], 'C'], [[5, 5], 'D'], [[6, 6], 'E'], [[4, 8], 'E'],
    [[7, 7], 'O'], [[8, 8], 'O'], [[6, 9], 'O'], [[8, 9], 'F'], [[9, 9], 'F'],
    [[2, 2, 3], 'A'], [[3, 3, 4], 'B'], [[4, 4, 5], 'C'], [[5, 5, 6], 'D'], [[6, 6, 7], 'E'], [[8, 6, 5], 'E'],
    [[8, 8, 8], 'O'], [[9, 9, 7], 'O'], [[9, 9, 8], 'F'],
    [[3, 3, 3, 4], 'B'], [[8, 6, 6, 5], 'E'], [[9, 9, 7, 7], 'O'], [[9, 9, 8, 8], 'F'],
    [[1], 'A'], [[5], 'D'],
]);
