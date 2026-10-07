<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per printed document that carries a QR code: what the public
 * check page at /verify/{code} confirms (a summary kept as printed), and
 * whether a newer version has replaced it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->string('type', 30);
            // What the document is about, e.g. "report_card:{student}:{term}:{exam}".
            $table->string('subject_key', 120);
            $table->json('summary');
            $table->string('fingerprint', 64);
            $table->timestamp('replaced_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'subject_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_verifications');
    }
};
