<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Admitting, changing and removing learners became its own module,
 * "Admissions"; "Students" now only shows them. Anyone who was given
 * Students by hand and is not a teacher (a secretary, say) keeps the
 * admitting they could do before. Teachers only see learners now.
 */
return new class extends Migration
{
    public function up(): void
    {
        User::query()
            ->whereNotNull('modules')
            ->with('roles')
            ->each(function (User $user): void {
                $modules = is_array($user->modules) ? $user->modules : [];

                if (in_array('students', $modules, true)
                    && ! in_array('admissions', $modules, true)
                    && ! $user->hasRole('Teacher')) {
                    $user->forceFill(['modules' => [...$modules, 'admissions']])->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        // Leaves the module in place: it is harmless without the code that reads it.
    }
};
