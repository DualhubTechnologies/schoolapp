<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('parent_student_login')->default(true)->after('max_users');
            // "Talk to sales" instead of a listed price / self-serve trial (e.g. Enterprise).
            $table->boolean('contact_sales')->default(false)->after('price_per_year');
        });

        DB::table('plans')->whereIn('slug', ['starter', 'standard'])->update(['parent_student_login' => false]);
        DB::table('plans')->where('slug', 'enterprise')->update(['contact_sales' => true]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['parent_student_login', 'contact_sales']);
        });
    }
};
