<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The admissions office (often the school secretary): admits learners,
 * keeps their and their parents' details, prints ID cards and texts
 * parents (Modules::ROLE_DEFAULTS).
 */
return new class extends Migration
{
    public function up(): void
    {
        Role::findOrCreate('Admissions', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Kept: users may already hold the role.
    }
};
