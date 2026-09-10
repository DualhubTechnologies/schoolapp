<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('UGX');
            $table->date('paid_on');
            $table->string('method')->default('cash'); // cash | bank | mobile_money | schoolpay
            $table->string('reference')->nullable();   // receipt / txn ref; SchoolPay txn id later
            $table->string('recorded_by')->nullable(); // user name/id who entered it
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'student_id']);
            $table->index('paid_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_payments');
    }
};
