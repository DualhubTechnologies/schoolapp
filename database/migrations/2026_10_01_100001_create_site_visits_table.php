<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visits to the public website (home, About, Features, Pricing, Help,
 * Contact), for the platform owner's Website visitors page. No IP address
 * is kept: `visitor` is a one-way code that is the same for one browser
 * on one day, which is enough to count unique visitors and no more.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->date('visited_on');
            $table->string('path', 100);
            $table->char('visitor', 64);
            $table->char('country_code', 2)->nullable();
            $table->string('country', 80)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('source', 30);
            $table->string('referrer_host', 120)->nullable();
            $table->string('device', 10);
            $table->timestamp('created_at')->nullable();

            $table->index(['visited_on', 'path']);
            $table->index(['visited_on', 'visitor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visits');
    }
};
