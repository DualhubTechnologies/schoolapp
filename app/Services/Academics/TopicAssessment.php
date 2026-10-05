<?php

namespace App\Services\Academics;

use App\Models\Assessment;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\SyllabusTopic;
use App\Models\Term;
use App\Models\TopicScore;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * NCDC topic assessment for the new lower-secondary curriculum. Teachers
 * record each learner's level (0-3) per syllabus topic; the average level
 * for a subject this term, out of 3, becomes the learner's mark in the
 * term's "Topic assessment" exam, so it counts as the school-based share
 * of the term result (20% by default) like any other mark.
 */
class TopicAssessment
{
    public const ASSESSMENT_TYPE = 'topics';

    /** The weight a new Topic assessment exam gets when nothing else is school-based. */
    public const DEFAULT_WEIGHT = 20;

    /**
     * The subject's topics for this class (S.2 -> class number 2); none
     * until both are chosen.
     *
     * @return EloquentCollection<int, SyllabusTopic>
     */
    public function topicsFor(?SchoolClass $class, ?Subject $subject): EloquentCollection
    {
        return SyllabusTopic::query()
            ->when(! $class || ! $subject, fn ($q) => $q->whereRaw('0 = 1'))
            ->where('school_id', $class?->school_id)
            ->where('subject_id', $subject?->getKey())
            ->where('class_number', $class?->number())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Save levels for the topics shown and update each learner's term mark.
     * A blank level removes a score recorded earlier.
     *
     * @param  array<int, array<int, int|string|null>>  $levels  student id => topic id => level
     * @param  array<int, int>  $topicIds  the topics the teacher was shown
     * @return int scores saved
     */
    public function record(Term $term, Subject $subject, array $levels, array $topicIds, ?int $userId): int
    {
        $saved = 0;

        DB::transaction(function () use ($term, $subject, $levels, $topicIds, $userId, &$saved) {
            foreach ($levels as $studentId => $byTopic) {
                foreach ($topicIds as $topicId) {
                    $level = $byTopic[$topicId] ?? null;
                    $match = ['student_id' => (int) $studentId, 'syllabus_topic_id' => $topicId];

                    if ($level === null || $level === '') {
                        TopicScore::where($match)->delete();

                        continue;
                    }

                    TopicScore::updateOrCreate($match, [
                        'school_id' => $term->school_id,
                        'term_id' => $term->getKey(),
                        'subject_id' => $subject->getKey(),
                        'level' => max(0, min(3, (int) $level)),
                        'entered_by' => $userId,
                    ]);
                    $saved++;
                }

                $this->syncMark($term, $subject, (int) $studentId, $userId);
            }
        });

        return $saved;
    }

    /**
     * The term's Topic assessment exam, created the first time topics are
     * recorded. It carries the school-based 20% unless the school already
     * gives that weight to Activities of Integration or project work.
     */
    public function termAssessment(Term $term): Assessment
    {
        $existing = Assessment::where('term_id', $term->getKey())->where('type', self::ASSESSMENT_TYPE)->first();

        if ($existing) {
            return $existing;
        }

        $otherSchoolBased = Assessment::where('term_id', $term->getKey())
            ->whereIn('type', ['ca', 'project'])
            ->where(fn ($q) => $q->whereNull('curriculum')->orWhere('curriculum', 'o_level'))
            ->where('weight', '>', 0)
            ->exists();

        return Assessment::create([
            'school_id' => $term->school_id,
            'term_id' => $term->getKey(),
            'name' => 'Topic assessment',
            'type' => self::ASSESSMENT_TYPE,
            'curriculum' => 'o_level',
            'max_score' => 3,
            'weight' => $otherSchoolBased ? 0 : self::DEFAULT_WEIGHT,
            'sort_order' => 0,
        ]);
    }

    /** The learner's average level in the subject this term, as their Topic assessment mark. */
    public function syncMark(Term $term, Subject $subject, int $studentId, ?int $userId): void
    {
        $average = TopicScore::where('term_id', $term->getKey())
            ->where('subject_id', $subject->getKey())
            ->where('student_id', $studentId)
            ->avg('level');

        $assessment = $this->termAssessment($term);
        $match = ['assessment_id' => $assessment->getKey(), 'student_id' => $studentId, 'subject_id' => $subject->getKey()];

        if ($average === null) {
            Mark::where($match)->delete();

            return;
        }

        Mark::updateOrCreate($match, ['score' => round((float) $average, 2), 'is_absent' => false, 'entered_by' => $userId]);
    }

    /**
     * Topic levels for report cards: student id => subject id => scores,
     * in syllabus order.
     *
     * @param  array<int, mixed>  $studentIds
     * @return array<int, array<int, list<TopicScore>>>
     */
    public function forReport(Term $term, array $studentIds): array
    {
        $out = [];

        $scores = TopicScore::where('term_id', $term->getKey())
            ->whereIn('student_id', $studentIds)
            ->with('topic')
            ->get()
            ->sortBy(fn (TopicScore $score): array => [$score->topic->sort_order, $score->syllabus_topic_id]);

        foreach ($scores as $score) {
            $out[$score->student_id][$score->subject_id][] = $score;
        }

        return $out;
    }
}
