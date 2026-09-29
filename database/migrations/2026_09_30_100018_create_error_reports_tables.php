<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Errors the platform owner can see in the admin: one report per distinct
 * error (same exception, file and line), and each time it happened -- with
 * the reference shown to the person it happened to, so they can quote it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_reports', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 64)->unique();
            $table->string('exception_class');
            $table->text('message');
            $table->string('file', 500);
            $table->unsignedInteger('line');
            $table->text('trace')->nullable();
            $table->unsignedInteger('occurrences')->default(0);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->string('last_url', 2048)->nullable();
            $table->foreignId('last_school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('last_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('last_seen_at');
        });

        Schema::create('error_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('error_report_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 16)->unique();
            $table->string('url', 2048)->nullable();
            $table->string('method', 10)->nullable();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_occurrences');
        Schema::dropIfExists('error_reports');
    }
};
