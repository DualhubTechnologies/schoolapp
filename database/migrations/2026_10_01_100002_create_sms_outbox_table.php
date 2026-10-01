<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Texts the Windows app could not send because the computer was offline.
 * `sms:send-queued` (every five minutes) sends them once it is back online.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('to', 20);
            $table->text('message');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('last_error', 255)->nullable();
            $table->string('ref', 100)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['sent_at', 'failed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_outbox');
    }
};
