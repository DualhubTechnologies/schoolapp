<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The storekeeper runs the school stores and keeps the asset register
 * (Stores & assets); see Modules::ROLE_DEFAULTS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Role::findOrCreate('Storekeeper', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Kept: users may already hold the role.
    }
};
