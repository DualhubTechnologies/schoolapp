<?php

namespace App\Support;

use App\Models\Section;
use App\Models\Staff;

/**
 * Who does what in academics:
 *
 *   School Admin  sets up subjects, grading, exams; enters any marks;
 *                 writes head-teacher comments; prints report cards
 *   Teacher       enters marks only for the class subjects assigned to them
 *   Class teacher (of a stream) also enters any subject's marks for that
 *                 stream, and writes its class-teacher comments
 */
class AcademicAccess
{
    /**
     * Manages exams: creates and locks them, enters marks for any subject,
     * writes head-teacher comments. School Admins, and anyone given
     * "Marks for all subjects" (e.g. the Director of Studies).
     */
    public static function manages(): bool
    {
        return Modules::hasFullAccess() || Modules::allows('exams_all');
    }

    /** Works in Exams & Results at all (own subjects, results, report cards). */
    public static function teaches(): bool
    {
        return Modules::allows('exams') || Modules::allows('exams_all');
    }

    /**
     * The signed-in user's staff record, if they have one.
     */
    public static function staffId(): ?int
    {
        $userId = auth()->id();

        return $userId ? once(fn () => Staff::where('user_id', $userId)->value('id')) : null;
    }

    /**
     * Streams the signed-in user is class teacher of: section id => class id.
     *
     * @return array<int, int>
     */
    public static function classTeacherStreams(): array
    {
        $staffId = static::staffId();

        return $staffId
            ? once(fn () => Section::where('class_teacher_id', $staffId)->pluck('school_class_id', 'id')->all())
            : [];
    }

    /**
     * The streams of a class the user is class teacher of.
     *
     * @return list<int>
     */
    public static function classTeacherStreamsIn(?int $classId): array
    {
        return array_keys(array_filter(static::classTeacherStreams(), fn ($c) => $c === $classId));
    }

    /**
     * May the user enter marks for a class subject taught by $teacherId?
     * Admins may enter any; a teacher only those assigned to them; a
     * class teacher any subject of their class (for their stream only --
     * see streamsForMarks()).
     */
    public static function canEnterMarksFor(?int $teacherId, ?int $classId = null): bool
    {
        return static::streamsForMarks($teacherId, $classId) !== [];
    }

    /**
     * Which streams' marks the user may enter for a class subject:
     * null = every stream, a list = only those streams, [] = none.
     *
     * @return list<int>|null
     */
    public static function streamsForMarks(?int $teacherId, ?int $classId = null): ?array
    {
        if (static::manages()) {
            return null;
        }

        if (! static::teaches()) {
            return [];
        }

        if ($teacherId !== null && $teacherId === static::staffId()) {
            return null;
        }

        return static::classTeacherStreamsIn($classId);
    }
}
