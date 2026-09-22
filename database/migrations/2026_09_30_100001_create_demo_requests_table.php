<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demo bookings and enquiries sent from the landing page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('school_name', 150);
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->string('learners', 20)->nullable();        // a range, e.g. "300-800"
            $table->date('preferred_date')->nullable();
            $table->string('preferred_contact', 20)->default('whatsapp');   // whatsapp, call, email
            $table->text('message')->nullable();
            $table->string('status', 20)->default('new');       // new, contacted, booked, closed
            $table->text('notes')->nullable();                  // the team's own notes
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
    }
};
