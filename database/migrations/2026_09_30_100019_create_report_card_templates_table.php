<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each school's report card template: design, colours, border, header and
 * footer wording, and which parts of the card are printed. A school that
 * never saves one gets the defaults (App\Models\ReportCardTemplate).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_card_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('design', 20)->default('classic');
            $table->string('font', 20)->default('sans');
            $table->string('border', 20)->default('none');
            $table->string('primary_color', 7)->default('#1e3a5f');
            $table->string('accent_color', 7)->default('#c8a24a');
            $table->string('title', 80)->nullable();
            $table->string('header_note', 160)->nullable();
            $table->string('footer_text', 300)->nullable();
            $table->boolean('watermark')->default(false);
            $table->json('show')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_card_templates');
    }
};
