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
use App\Services\Academics\ResultsCalculator;

beforeEach(function () {
    $this->school = School::create(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'school_type' => 'secondary']);
    $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'is_current' => true]);
    $this->term = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Term 3', 'sequence' => 3, 'is_current' => true]);
    $level = ClassLevel::create(['school_id' => $this->school->id, 'name' => 'O-Level', 'curriculum' => 'o_level']);
    $this->class = SchoolClass::create(['school_id' => $this->school->id, 'class_level_id' => $level->id, 'name' => 'S.4']);

    $scale = GradingScale::create(['school_id' => $this->school->id, 'curriculum' => 'o_level', 'purpose' => 'subject', 'name' => 'O-Level']);
    foreach (config('academics.grading.o_level.subject') as $i => [$grade, $min, $max, $value, $descriptor]) {
        $scale->bands()->create(['grade' => $grade, 'min_score' => $min, 'max_score' => $max, 'value' => $value, 'descriptor' => $descriptor, 'sort_order' => $i + 1]);
    }

    // Eight subjects, the first seven compulsory.
    $this->subjects = collect(['English', 'Mathematics', 'Biology', 'Chemistry', 'Physics', 'Geography', 'History', 'ICT'])
        ->map(function (string $name, int $i) {
            $subject = Subject::create(['school_id' => $this->school->id, 'name' => $name, 'curriculum' => 'o_level']);
            $this->class->subjects()->attach($subject->id, ['is_compulsory' => $i < 7]);

            return $subject;
        });

    $this->project = Assessment::create(['school_id' => $this->school->id, 'term_id' => $this->term->id, 'name' => 'Project work', 'type' => 'project', 'max_score' => 3, 'weight' => 20]);
    $this->exam = Assessment::create(['school_id' => $this->school->id, 'term_id' => $this->term->id, 'name' => 'End of year', 'type' => 'eot', 'max_score' => 100, 'weight' => 80]);
});

function uceLearner(string $name): Student
{
    return Student::create(['school_id' => test()->school->id, 'school_class_id' => test()->class->id, 'name' => $name, 'admission_no' => 'ADM-'.$name, 'status' => 'active']);
}

/**
 * @param  array<string, float>  $examScores  subject name => exam score; others get $default
 */
function uceSit(Student $student, float $default, array $examScores = [], array $skip = [], array $noProject = []): void
{
    foreach (test()->subjects as $subject) {
        if (in_array($subject->name, $skip, true)) {
            continue;
        }

        Mark::create(['assessment_id' => test()->exam->id, 'student_id' => $student->id, 'subject_id' => $subject->id, 'score' => $examScores[$subject->name] ?? $default]);

        if (! in_array($subject->name, $noProject, true)) {
            Mark::create(['assessment_id' => test()->project->id, 'student_id' => $student->id, 'subject_id' => $subject->id, 'score' => $default >= 30 ? 2 : 0]);
        }
    }
}

function uceResultFor(Student $student): array
{
    return app(ResultsCalculator::class)->forClass(test()->class, test()->term)['rows']->firstWhere('student.id', $student->id);
}

it('gives Result 1 to a learner who sat every subject with project scores and got D or better', function () {
    uceSit($sarah = uceLearner('Sarah'), 20, ['English' => 60]);

    expect(uceResultFor($sarah))
        ->uce_result->toBe(1)
        ->uce_label->toBe('Result 1')
        ->uce_reasons->toBe([]);
});

it('gives Result 2 and says why when a requirement is missing', function () {
    uceSit($brian = uceLearner('Brian'), 70, skip: ['Chemistry'], noProject: ['ICT']);
    uceSit(uceLearner('Other'), 70); // the class has project scores in every subject

    $row = uceResultFor($brian);

    expect($row['uce_result'])->toBe(2)
        ->and($row['uce_reasons'])->toBe([
            'Graded in 7 of the 8 subjects needed',
            'No marks in Chemistry',
            'No project / continuous assessment score in ICT',
        ]);
});

it('gives Result 3 when every subject is at the lowest level', function () {
    uceSit($eve = uceLearner('Eve'), 10);

    expect(uceResultFor($eve)['uce_result'])->toBe(3);
});

it('only asks for project scores in subjects the class has them for', function () {
    Mark::where('assessment_id', $this->project->id)->delete();
    uceSit($sarah = uceLearner('Sarah'), 70, noProject: test()->subjects->pluck('name')->all());

    expect(uceResultFor($sarah)['uce_result'])->toBe(1);
});
