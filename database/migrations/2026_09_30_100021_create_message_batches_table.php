<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk SMS a school sends (to all families, a class, those owing fees, or
 * staff): what was sent, to whom, by whom, and how many went through.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('audience', 30);                 // families | class | owing | staff
            $table->json('filters')->nullable();            // class_id, section_id
            $table->string('audience_label');              // "Parents of P.4 East"
            $table->text('body');
            $table->string('status', 20)->default('queued'); // queued | sending | done | failed
            $table->unsignedInteger('recipients')->default(0);
            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('no_phone')->default(0);
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_batches');
    }
};
