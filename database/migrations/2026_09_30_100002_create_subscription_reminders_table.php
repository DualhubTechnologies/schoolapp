<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reminders sent to schools about their trial or subscription ending, so
 * each one goes out once. Keyed by the end date it was about: renewing
 * moves the end date and so starts a fresh set of reminders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->date('ends_on');
            $table->string('kind', 20);            // ends-in-14 ... ends-today, ended, locks-soon, locked
            $table->unsignedSmallInteger('emails')->default(0);
            $table->boolean('sms')->default(false);
            $table->string('note')->nullable();    // e.g. why an SMS failed
            $table->timestamps();

            $table->unique(['school_id', 'ends_on', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_reminders');
    }
};
