<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('alt_phone')->nullable();
            $table->string('email')->nullable();
            $table->string('relationship')->default('guardian');
            $table->string('occupation')->nullable();
            $table->string('national_id')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'name']);
            $table->index(['school_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};