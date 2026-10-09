<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The bursar is usually the first login a school needs after its head:
 * fees and spending, without payroll (Modules::ROLE_DEFAULTS).
 */
return new class extends Migration
{
    public function up(): void
    {
        Role::findOrCreate('Bursar', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Kept: users may already hold the role.
    }
};
