<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Uganda's national school terms (Ministry of Education and Sports). A paid
 * SchoolHub term runs to the day before the next term opens, so schools
 * pay at the start of a term. The platform owner keeps the dates up to
 * date in the admin panel; "estimate" marks dates not yet published.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_calendar_terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('term');
            $table->date('opens_on');
            $table->date('closes_on');
            $table->boolean('is_estimate')->default(false);
            $table->timestamps();

            $table->unique(['year', 'term']);
        });

        $now = now();

        DB::table('school_calendar_terms')->insert(array_map(fn (array $row): array => [
            'year' => $row[0], 'term' => $row[1], 'opens_on' => $row[2], 'closes_on' => $row[3], 'is_estimate' => $row[4],
            'created_at' => $now, 'updated_at' => $now,
        ], [
            // 2026: the Ministry's calendar, Term I moved to 10 Feb for the elections.
            [2026, 1, '2026-02-10', '2026-05-01', false],
            [2026, 2, '2026-05-25', '2026-08-21', false],
            [2026, 3, '2026-09-14', '2026-12-04', false],
            // 2027 and 2028: estimated from 2026 until the Ministry publishes them.
            [2027, 1, '2027-02-01', '2027-04-30', true],
            [2027, 2, '2027-05-24', '2027-08-20', true],
            [2027, 3, '2027-09-13', '2027-12-03', true],
            [2028, 1, '2028-02-07', '2028-05-05', true],
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('school_calendar_terms');
    }
};
