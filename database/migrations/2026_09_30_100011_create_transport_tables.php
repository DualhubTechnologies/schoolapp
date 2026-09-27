<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * School transport (the van). A school defines its routes, each with a
 * termly fare; a learner either uses a route or is brought by the parent.
 * Billing adds the fare as its own line, linked to the route, so transport
 * money can be told apart from school fees.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('fare', 12, 2);
            $table->decimal('one_way_fare', 12, 2)->nullable();
            $table->string('vehicle', 50)->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_phone', 30)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('transport_route_id')->nullable()->after('residency_type_id')
                ->constrained('transport_routes')->nullOnDelete();
            $table->string('transport_trip', 10)->nullable()->after('transport_route_id');
        });

        Schema::table('student_charges', function (Blueprint $table) {
            $table->foreignId('transport_route_id')->nullable()->after('fee_structure_id')
                ->constrained('transport_routes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_charges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transport_route_id');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transport_route_id');
            $table->dropColumn('transport_trip');
        });

        Schema::dropIfExists('transport_routes');
    }
};
