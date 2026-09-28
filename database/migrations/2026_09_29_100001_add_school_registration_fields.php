<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schools can register themselves; the platform owner approves them.
 */
return new class extends Migration
{
    public function up(): void
    {
        // pending = registered, awaiting approval; rejected = turned down.
        Schema::table('schools', function (Blueprint $table) {
            $table->enum('status', ['pending', 'active', 'suspended', 'inactive', 'rejected'])->default('active')->change();
            $table->string('boarding_type', 20)->nullable()->after('school_type');   // day, boarding, mixed
            $table->string('ownership', 20)->nullable()->after('boarding_type');     // government, private, community...
            $table->unsignedInteger('expected_students')->nullable()->after('ownership');
            $table->string('contact_person')->nullable()->after('phone');
            $table->string('contact_title')->nullable()->after('contact_person');
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by')->nullable();
            $table->string('rejection_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['boarding_type', 'ownership', 'expected_students', 'contact_person', 'contact_title', 'approved_at', 'approved_by', 'rejection_reason']);
        });

        DB::statement("UPDATE schools SET status = 'inactive' WHERE status IN ('pending','rejected')");

        Schema::table('schools', function (Blueprint $table) {
            $table->enum('status', ['active', 'suspended', 'inactive'])->default('active')->change();
        });
    }
};
