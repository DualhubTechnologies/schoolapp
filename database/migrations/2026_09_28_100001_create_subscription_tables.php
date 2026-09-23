<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->unsignedInteger('max_students')->nullable();   // null = unlimited
            $table->unsignedInteger('max_users')->nullable();      // null = unlimited
            $table->decimal('price_per_term', 12, 0)->default(0);
            $table->decimal('price_per_year', 12, 0)->default(0);
            $table->boolean('is_trial')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('cycle', 10);                 // trial, term, year, custom
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('amount', 12, 0)->default(0);
            $table->boolean('is_cancelled')->default(false);
            $table->string('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'starts_on']);
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 0);
            $table->string('method', 20);                // mobile_money, bank, cash, cheque
            $table->string('reference')->nullable();
            $table->date('paid_on');
            $table->string('received_by')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        // Starting plans -- the platform owner edits prices and limits in
        // /admin. Every plan has every feature.
        $now = now();
        DB::table('plans')->insert([
            ['name' => 'Free Trial', 'slug' => 'trial', 'description' => 'Try everything for 30 days.', 'max_students' => 1000, 'max_users' => 10, 'price_per_term' => 0, 'price_per_year' => 0, 'is_trial' => true, 'sort_order' => 0],
            ['name' => 'Starter', 'slug' => 'starter', 'description' => 'Small schools.', 'max_students' => 300, 'max_users' => 10, 'price_per_term' => 160000, 'price_per_year' => 432000, 'is_trial' => false, 'sort_order' => 1],
            ['name' => 'Standard', 'slug' => 'standard', 'description' => 'Most day and boarding schools.', 'max_students' => 800, 'max_users' => 25, 'price_per_term' => 300000, 'price_per_year' => 810000, 'is_trial' => false, 'sort_order' => 2],
            ['name' => 'Premium', 'slug' => 'premium', 'description' => 'Large schools with several streams.', 'max_students' => 1500, 'max_users' => 60, 'price_per_term' => 500000, 'price_per_year' => 1350000, 'is_trial' => false, 'sort_order' => 3],
            ['name' => 'Enterprise', 'slug' => 'enterprise', 'description' => 'No limits.', 'max_students' => null, 'max_users' => null, 'price_per_term' => 900000, 'price_per_year' => 2430000, 'is_trial' => false, 'sort_order' => 4],
        ]);
        DB::table('plans')->update(['created_at' => $now, 'updated_at' => $now]);

        // Schools already using the system get a trial so nobody is locked
        // out on the day this ships.
        $trial = DB::table('plans')->where('slug', 'trial')->value('id');
        $days = (int) config('subscriptions.trial_days', 30);

        foreach (DB::table('schools')->pluck('id') as $schoolId) {
            DB::table('subscriptions')->insert([
                'school_id' => $schoolId,
                'plan_id' => $trial,
                'cycle' => 'trial',
                'starts_on' => $now->toDateString(),
                'ends_on' => $now->copy()->addDays($days - 1)->toDateString(),
                'amount' => 0,
                'notes' => 'Trial started when subscriptions were introduced.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
