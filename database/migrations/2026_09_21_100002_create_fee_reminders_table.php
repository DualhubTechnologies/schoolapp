<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every fee reminder sent -- by SMS or printed letter -- so the bursar can
 * see who has been reminded, when, and whether the SMS went through.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20);            // sms | letter
            $table->string('phone', 30)->nullable();
            $table->decimal('balance', 12, 2);         // what was owed when reminded
            $table->text('message');
            $table->string('status', 20);             // sent | failed | printed
            $table->string('error')->nullable();
            $table->string('provider_ref')->nullable();
            $table->string('sent_by')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'created_at']);
            $table->index(['student_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_reminders');
    }
};
