<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The librarian lends books (Library) and the school nurse runs the sick
 * bay (Sick bay); see Modules::ROLE_DEFAULTS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Role::findOrCreate('Librarian', 'web');
        Role::findOrCreate('Nurse', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Kept: users may already hold the roles.
    }
};
