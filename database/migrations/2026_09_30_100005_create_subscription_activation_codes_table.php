<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_activation_codes', function (Blueprint $table) {
            $table->id();
            // Null until redeemed: a code can be generated ahead of time as
            // stock (not yet tied to any school) and claimed by whichever
            // school types it in first.
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('code')->unique();

            // What redeeming it does -- the same inputs SubscriptionManager::renew() takes.
            $table->string('cycle', 10);
            $table->decimal('amount', 12, 0)->default(0);
            $table->decimal('payment_amount', 12, 0)->default(0);
            $table->string('payment_method', 20)->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('payment_notes')->nullable();
            $table->date('custom_ends_on')->nullable();
            $table->string('notes')->nullable();

            $table->date('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('used_by')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('created_by')->nullable();

            $table->timestamps();

            $table->index(['school_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_activation_codes');
    }
};
