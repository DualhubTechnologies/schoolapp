<?php

namespace App\Services\Academics;

use App\Models\Combination;
use App\Models\GradingScale;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Support\SchoolType;
use Illuminate\Support\Facades\DB;

/**
 * Gives a school the Ugandan curriculum defaults (config/academics.php):
 * subjects for each curriculum its classes follow, which subjects each
 * class takes, grading scales and -- for A-Level -- combinations.
 *
 * Safe to run again: it only adds what is missing and never overwrites
 * anything the school has edited.
 */
class CurriculumSetup
{
    /**
     * @return array{subjects: int, class_subjects: int, scales: int, combinations: int, curricula: list<string>}
     */
    public function run(School $school): array
    {
        $result = ['subjects' => 0, 'class_subjects' => 0, 'scales' => 0, 'combinations' => 0, 'curricula' => []];

        $classes = SchoolClass::where('school_id', $school->getKey())->with('classLevel')->get();
        $allowed = SchoolType::keys($school);
        $curricula = $classes->map->curriculum()->filter(fn ($c) => in_array($c, $allowed, true))->unique()->values();

        // A school whose levels carry no curriculum yet: go by school type.
        if ($curricula->isEmpty()) {
            $curricula = collect($school->school_type === School::TYPE_PRIMARY ? ['primary'] : ['o_level', 'a_level']);
        }

        $result['curricula'] = $curricula->all();

        DB::transaction(function () use ($school, $classes, $curricula, &$result) {
            foreach ($curricula as $curriculum) {
                $subjects = $this->subjects($school, $curriculum, $result);
                $this->scales($school, $curriculum, $result);

                foreach ($classes->filter(fn (SchoolClass $c) => $c->curriculum() === $curriculum) as $class) {
                    $this->assign($class, $curriculum, $subjects, $result);
                }

                if ($curriculum === 'a_level') {
                    $this->combinations($school, $subjects, $result);
                }
            }
        });

        return $result;
    }

    /**
     * @return array<string, array{subject: Subject, spec: array}> subject name => model + its defaults
     */
    protected function subjects(School $school, string $curriculum, array &$result): array
    {
        $out = [];

        foreach (config("academics.subjects.{$curriculum}", []) as $order => $spec) {
            $subject = Subject::firstOrCreate(
                ['school_id' => $school->getKey(), 'curriculum' => $curriculum, 'name' => $spec['name']],
                [
                    'short_name' => $spec['short'] ?? null,
                    'category' => $spec['category'] ?? 'standard',
                    'sort_order' => $order + 1,
                    'is_active' => true,
                ],
            );

            $result['subjects'] += (int) $subject->wasRecentlyCreated;
            $out[$spec['name']] = ['subject' => $subject, 'spec' => $spec];
        }

        return $out;
    }

    protected function scales(School $school, string $curriculum, array &$result): void
    {
        foreach (config("academics.grading.{$curriculum}", []) as $purpose => $bands) {
            $scale = GradingScale::firstOrCreate(
                ['school_id' => $school->getKey(), 'curriculum' => $curriculum, 'purpose' => $purpose],
                ['name' => (config('academics.curricula')[$curriculum] ?? $curriculum).' — '.(GradingScale::PURPOSES[$purpose] ?? $purpose)],
            );

            if (! $scale->wasRecentlyCreated) {
                continue;
            }

            foreach ($bands as $i => [$grade, $min, $max, $value, $descriptor]) {
                $scale->bands()->create([
                    'grade' => $grade,
                    'min_score' => $min,
                    'max_score' => $max,
                    'value' => $value,
                    'descriptor' => $descriptor,
                    'sort_order' => $i + 1,
                ]);
            }

            $result['scales']++;
        }
    }

    /**
     * Attach the subjects this class takes. Primary and O-Level follow
     * each subject's 'offered_in' / 'compulsory_in' class numbers; A-Level
     * classes get every A-Level subject (students take theirs through
     * their combination).
     */
    protected function assign(SchoolClass $class, string $curriculum, array $subjects, array &$result): void
    {
        $number = $class->number();
        $existing = $class->subjects()->pluck('subjects.id')->all();

        foreach ($subjects as ['subject' => $subject, 'spec' => $spec]) {
            if ($curriculum === 'a_level') {
                $offered = true;
                $compulsory = (bool) ($spec['compulsory'] ?? false);
            } else {
                $offered = ! isset($spec['offered_in']) || in_array($number, $spec['offered_in'], true);
                $compulsory = in_array($number, $spec['compulsory_in'] ?? ($spec['offered_in'] ?? [$number]), true);
            }

            if (! $offered || in_array($subject->getKey(), $existing, true)) {
                continue;
            }

            $class->subjects()->attach($subject->getKey(), ['is_compulsory' => $compulsory]);
            $result['class_subjects']++;
        }
    }

    protected function combinations(School $school, array $subjects, array &$result): void
    {
        $subMath = $subjects['Subsidiary Mathematics']['subject'] ?? null;
        $subIct = $subjects['Subsidiary ICT']['subject'] ?? null;

        foreach (config('academics.combinations', []) as $code => $names) {
            $principals = collect($names)->map(fn ($n) => $subjects[$n]['subject'] ?? null)->filter();

            if ($principals->count() !== count($names)) {
                continue; // a subject the school has removed
            }

            $combination = Combination::firstOrCreate(
                ['school_id' => $school->getKey(), 'name' => $code],
                [
                    'description' => implode(', ', $names),
                    'subsidiary_subject_id' => (in_array('Mathematics', $names, true) ? $subIct : $subMath)?->getKey(),
                    'is_active' => true,
                ],
            );

            if ($combination->wasRecentlyCreated) {
                $combination->subjects()->sync($principals->pluck('id'));
                $result['combinations']++;
            }
        }
    }
}
