<?php

namespace App\Observers;

use App\Models\ClassLevel;
use App\Models\School;

class SchoolObserver
{
    /**
     * Give a new school the levels its type implies, so the admin can add
     * classes immediately instead of meeting an empty screen and guessing
     * what to type.
     *
     * They are ordinary records — rename, reorder or delete them freely.
     */
    public function created(School $school): void
    {
        $this->seedLevels($school);
    }

    /**
     * If the type is set later, or corrected, seed then instead. Existing
     * levels are left alone: the school may have edited them, and quietly
     * replacing someone's work is worse than doing nothing.
     */
    public function updated(School $school): void
    {
        if ($school->wasChanged('school_type')) {
            $this->seedLevels($school);
        }
    }

    protected function seedLevels(School $school): void
    {
        if (! $school->school_type) {
            return;
        }

        if ($school->classLevels()->exists()) {
            return;
        }

        foreach (ClassLevel::DEFAULTS[$school->school_type] ?? [] as $index => $name) {
            ClassLevel::create([
                'school_id' => $school->getKey(),
                'name' => $name,
                'curriculum' => ClassLevel::DEFAULT_CURRICULA[$name] ?? null,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
