<?php

namespace App\Filament\App\Resources\Users\Pages\Concerns;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Saves the parts of the user form that are not plain user columns:
 * whether modules were chosen, the linked staff record, and the class
 * subjects the person teaches (class_subject.teacher_id).
 */
trait SyncsUserAccess
{
    /**
     * Form data -> user columns: modules are only stored when the
     * administrator chose them; otherwise null = the role's defaults.
     */
    protected function prepareAccess(array $data): array
    {
        $raw = $this->form->getRawState();

        $data['modules'] = ($raw['custom_access'] ?? false)
            ? array_values($raw['modules'] ?? [])
            : null;

        $data['school_id'] ??= auth()->user()->school_id;

        return $data;
    }

    protected function syncStaffAndTeaching(User $user): void
    {
        $raw = $this->form->getRawState();
        $staffId = $raw['staff_id'] ?? null;

        DB::transaction(function () use ($user, $staffId, $raw) {
            // One staff record per login.
            Staff::where('user_id', $user->getKey())->where('id', '!=', $staffId)->update(['user_id' => null]);

            $staff = $staffId ? Staff::where('school_id', $user->school_id)->whereKey($staffId)->first() : null;

            if (! $staff) {
                return;
            }

            $staff->update(['user_id' => $user->getKey()]);

            $chosen = collect($raw['teaching'] ?? [])->map(fn ($id) => (int) $id);
            $schoolRows = DB::table('class_subject')
                ->join('school_classes', 'school_classes.id', '=', 'class_subject.school_class_id')
                ->where('school_classes.school_id', $user->school_id)
                ->pluck('class_subject.id');

            // Chosen subjects: this person teaches them now.
            DB::table('class_subject')
                ->whereIn('id', $chosen->intersect($schoolRows))
                ->update(['teacher_id' => $staff->getKey(), 'updated_at' => now()]);

            // Subjects they no longer teach: unassigned.
            DB::table('class_subject')
                ->whereIn('id', $schoolRows)
                ->where('teacher_id', $staff->getKey())
                ->whereNotIn('id', $chosen)
                ->update(['teacher_id' => null, 'updated_at' => now()]);

            // Class teacher of these streams (one class teacher per stream),
            // and of whole classes that have no streams ("class-{id}").
            $chosenTeacherOf = collect($raw['class_teacher_of'] ?? [])->map(fn ($id) => (string) $id);
            $streams = $chosenTeacherOf->reject(fn ($id) => str_starts_with($id, 'class-'))->map(fn ($id) => (int) $id);
            $classes = $chosenTeacherOf->filter(fn ($id) => str_starts_with($id, 'class-'))->map(fn ($id) => (int) substr($id, 6));
            $schoolClasses = DB::table('school_classes')->where('school_id', $user->school_id)->pluck('id');

            DB::table('school_classes')
                ->whereIn('id', $classes->intersect($schoolClasses))
                ->update(['class_teacher_id' => $staff->getKey(), 'updated_at' => now()]);

            DB::table('school_classes')
                ->whereIn('id', $schoolClasses)
                ->where('class_teacher_id', $staff->getKey())
                ->whereNotIn('id', $classes)
                ->update(['class_teacher_id' => null, 'updated_at' => now()]);

            $schoolStreams = DB::table('sections')->where('school_id', $user->school_id)->pluck('id');

            DB::table('sections')
                ->whereIn('id', $streams->intersect($schoolStreams))
                ->update(['class_teacher_id' => $staff->getKey(), 'updated_at' => now()]);

            DB::table('sections')
                ->whereIn('id', $schoolStreams)
                ->where('class_teacher_id', $staff->getKey())
                ->whereNotIn('id', $streams)
                ->update(['class_teacher_id' => null, 'updated_at' => now()]);
        });
    }

    protected function accessFormState(User $user): array
    {
        $staff = $user->staff;

        return [
            'custom_access' => $user->modules !== null,
            'staff_id' => $staff?->getKey(),
            'teaching' => $staff
                ? DB::table('class_subject')->where('teacher_id', $staff->getKey())->pluck('id')->map(fn ($id) => (string) $id)->all()
                : [],
            'class_teacher_of' => $staff
                ? [
                    ...DB::table('school_classes')->where('class_teacher_id', $staff->getKey())->pluck('id')->map(fn ($id) => "class-{$id}")->all(),
                    ...DB::table('sections')->where('class_teacher_id', $staff->getKey())->pluck('id')->map(fn ($id) => (string) $id)->all(),
                ]
                : [],
        ];
    }
}
