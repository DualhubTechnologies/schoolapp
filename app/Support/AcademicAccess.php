<?php

namespace App\Support;

use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

/**
 * Who does what in academics:
 *
 *   School Admin  sets up subjects, grading, exams; enters any marks;
 *                 writes head-teacher comments; prints report cards
 *   Teacher       enters marks only for the class subjects assigned to them
 *   Class teacher (of a stream, or of a whole class that has no streams)
 *                 also enters any subject's marks there, and writes its
 *                 class-teacher comments
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
        if (auth()->user()?->hasRole('Super Admin')) {
            return false;
        }

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

    /** Does the user teach any class subject, or class-teach any class or stream? */
    public static function hasTeachingLoad(): bool
    {
        $staffId = static::staffId();

        return $staffId !== null && (static::classTeacherClassIds() !== []
            || once(fn () => DB::table('class_subject')->where('teacher_id', $staffId)->exists()));
    }

    /**
     * Whole classes the signed-in user is class teacher of (classes
     * without streams).
     *
     * @return list<int>
     */
    public static function classTeacherWholeClasses(): array
    {
        $staffId = static::staffId();

        return $staffId
            ? once(fn () => array_values(SchoolClass::where('class_teacher_id', $staffId)->pluck('id')->map(fn ($id) => (int) $id)->all()))
            : [];
    }

    /**
     * Every class the user is class teacher in, whole or by stream.
     *
     * @return list<int>
     */
    public static function classTeacherClassIds(): array
    {
        return array_values(array_unique([...array_values(static::classTeacherStreams()), ...static::classTeacherWholeClasses()]));
    }

    /**
     * A teacher sees only their own learners: those in the classes they
     * teach a subject in, or are class teacher of (whole class or
     * stream). Anyone whose work covers the whole school (admin,
     * admissions, fees, Director of Studies, library, sick bay...) sees
     * every learner.
     */
    public static function seesOnlyOwnLearners(): bool
    {
        $user = auth()->user();

        if (! $user || $user->hasRole('Super Admin') || Modules::hasFullAccess($user)) {
            return false;
        }

        foreach (['admissions', 'fees', 'exams_all', 'library', 'sickbay', 'transport', 'id_cards', 'messages', 'promotion'] as $module) {
            if (Modules::allows($module)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Classes the user teaches a subject in, or is whole-class teacher of.
     *
     * @return list<int>
     */
    public static function taughtClassIds(): array
    {
        $staffId = static::staffId();

        if ($staffId === null) {
            return [];
        }

        return once(fn () => array_values(array_unique([
            ...DB::table('class_subject')->where('teacher_id', $staffId)->pluck('school_class_id')->map(fn ($id) => (int) $id)->all(),
            ...static::classTeacherWholeClasses(),
        ])));
    }

    /**
     * Narrows a query of learners (or rows with a learner's class and
     * stream) to the user's own learners, when seesOnlyOwnLearners().
     *
     * @template TQuery of \Illuminate\Database\Eloquent\Builder<*>|\Illuminate\Database\Query\Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public static function limitToOwnLearners($query, string $table = 'students')
    {
        if (! static::seesOnlyOwnLearners()) {
            return $query;
        }

        $classes = static::taughtClassIds();
        $streams = array_keys(static::classTeacherStreams());

        return $query->where(function ($q) use ($table, $classes, $streams): void {
            $q->whereIn("{$table}.school_class_id", $classes === [] ? [0] : $classes);

            if ($streams !== []) {
                $q->orWhereIn("{$table}.section_id", $streams);
            }
        });
    }

    /**
     * Narrows a query of classes to the user's own (taught, or class
     * teacher of, whole or by stream), when seesOnlyOwnLearners().
     *
     * @template TQuery of \Illuminate\Database\Eloquent\Builder<*>|\Illuminate\Database\Query\Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public static function limitToOwnClasses($query, string $column = 'id')
    {
        if (! static::seesOnlyOwnLearners()) {
            return $query;
        }

        $own = array_values(array_unique([...static::taughtClassIds(), ...static::classTeacherClassIds()]));

        $query->whereIn($column, $own === [] ? [0] : $own);

        return $query;
    }

    /**
     * Subjects the user teaches in any class.
     *
     * @return list<int>
     */
    public static function taughtSubjectIds(): array
    {
        $staffId = static::staffId();

        return $staffId === null ? [] : once(fn () => array_values(array_unique(
            DB::table('class_subject')->where('teacher_id', $staffId)->pluck('subject_id')->map(fn ($id) => (int) $id)->all()
        )));
    }

    /** Is the user class teacher of the whole class? */
    public static function isWholeClassTeacherOf(?int $classId): bool
    {
        return $classId !== null && in_array($classId, static::classTeacherWholeClasses(), true);
    }

    /**
     * May the user act as class teacher for this class and stream? A
     * whole-class teacher covers every stream (and "no stream").
     */
    public static function isClassTeacherOf(?int $classId, ?int $sectionId): bool
    {
        return static::isWholeClassTeacherOf($classId)
            || ($sectionId !== null && in_array($sectionId, static::classTeacherStreamsIn($classId), true));
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

        if (static::isWholeClassTeacherOf($classId)) {
            return null;
        }

        return static::classTeacherStreamsIn($classId);
    }
}
