<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each school's student ID card template: orientation, colours, how long
 * a card is valid and the rules printed on the (shared) back. Replaces
 * schools.id_card_template, carrying the landscape/portrait choice over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('id_card_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('orientation', 20)->default('landscape');
            $table->string('primary_color', 7)->default('#13294b');
            $table->string('accent_color', 7)->default('#c8a24a');
            $table->string('validity', 20)->default('academic_year');
            $table->unsignedTinyInteger('validity_months')->default(12);
            $table->text('back_notes')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('schools')->where('id_card_template', 'portrait')->pluck('id')
            ->each(fn ($schoolId) => DB::table('id_card_templates')->insert([
                'school_id' => $schoolId,
                'orientation' => 'portrait',
                'created_at' => $now,
                'updated_at' => $now,
            ]));

        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('id_card_template');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('id_card_template', 20)->default('classic')->after('hm_signature');
        });

        DB::table('id_card_templates')->where('orientation', 'portrait')->pluck('school_id')
            ->each(fn ($schoolId) => DB::table('schools')->where('id', $schoolId)->update(['id_card_template' => 'portrait']));

        Schema::dropIfExists('id_card_templates');
    }
};
