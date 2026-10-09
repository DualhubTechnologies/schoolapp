<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The Director of Studies runs the school's exams: sets them up, enters
 * or approves marks in every subject and class, and prints report cards
 * (Modules::ROLE_DEFAULTS). Before, they were a Teacher with "Marks for
 * all subjects" ticked by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Role::findOrCreate('Director of Studies', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Kept: users may already hold the role.
    }
};
