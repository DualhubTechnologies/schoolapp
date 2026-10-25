<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The school stores (things that get used up: posho, beans, sugar,
 * stationery, soap), every quantity in and out of them, and the asset
 * register (things the school owns: desks, computers, beds).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');                                   // e.g. Maize flour (posho)
            $table->string('category', 40)->default('food');          // StoreItem::CATEGORIES
            $table->string('unit', 30)->default('kg');
            $table->decimal('quantity', 12, 2)->default(0);           // in the store now
            $table->decimal('reorder_level', 12, 2)->nullable();      // warn at or below this
            $table->decimal('unit_cost', 12, 2)->nullable();          // last price paid per unit
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::create('store_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_item_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);                               // in | out | count
            $table->date('moved_on');
            $table->decimal('quantity', 12, 2);                       // in/out: amount moved; count: the change (+/-)
            $table->decimal('balance_after', 12, 2);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->decimal('total_cost', 14, 2)->nullable();
            $table->string('supplier')->nullable();                   // in
            $table->string('issued_to')->nullable();                  // out: kitchen, a department, a person
            $table->string('reference')->nullable();                  // delivery note, invoice or requisition no.
            $table->foreignId('finance_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recorded_by')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'moved_on']);
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('tag_seq');
            $table->string('tag_no', 20);                             // AST-0001, painted or stuck on the item
            $table->string('name');                                   // e.g. Student desk, HP laptop
            $table->string('category', 40)->default('furniture');     // Asset::CATEGORIES
            $table->unsignedInteger('quantity')->default(1);          // several identical items under one tag
            $table->string('location')->nullable();                   // room, block or office
            $table->string('condition', 20)->default('good');         // Asset::CONDITIONS
            $table->string('assigned_to')->nullable();                // who looks after it
            $table->string('serial_no')->nullable();
            $table->date('acquired_on')->nullable();
            $table->decimal('cost', 14, 2)->nullable();               // for all of them together
            $table->string('supplier')->nullable();
            $table->date('disposed_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'tag_seq']);
            $table->index(['school_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
        Schema::dropIfExists('store_movements');
        Schema::dropIfExists('store_items');
    }
};
