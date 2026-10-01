<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Licences for the Windows app.
 *
 *   issued_licences  on the online server: every key the platform owner
 *                    has issued, to look up, copy again or renew.
 *   licence_keys     in the Windows app: the keys its school has entered.
 *   app_state        in the Windows app: small values kept between runs,
 *                    such as the latest time the app has seen (clock check).
 *
 * Both editions run the same migrations; each table is simply empty where
 * it is not used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issued_licences', function (Blueprint $table) {
            $table->id();
            $table->string('licence_no', 30)->unique();
            $table->string('school_name', 150);
            $table->string('school_code', 20);
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan_name', 80);
            $table->unsignedInteger('max_students')->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->string('cycle', 10);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('payment_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->text('key');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_code', 'ends_on']);
        });

        Schema::create('licence_keys', function (Blueprint $table) {
            $table->id();
            $table->string('licence_no', 30)->unique();
            $table->text('key');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();
        });

        Schema::create('app_state', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->text('value')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_state');
        Schema::dropIfExists('licence_keys');
        Schema::dropIfExists('issued_licences');
    }
};
