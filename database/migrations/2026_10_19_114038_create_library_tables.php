<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The school library: each title, the numbered copies of it on the
 * shelves, and every loan of a copy to a learner or member of staff.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('category', 30)->default('textbook');   // LibraryBook::CATEGORIES
            $table->string('subject', 100)->nullable();
            $table->string('class_level', 50)->nullable();           // e.g. "S.2" or "P.5 – P.7"
            $table->string('isbn', 30)->nullable();
            $table->string('publisher')->nullable();
            $table->string('shelf', 50)->nullable();
            $table->decimal('price', 12, 2)->nullable();             // what a lost copy costs to replace
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'title']);
        });

        Schema::create('library_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('library_book_id')->constrained()->cascadeOnDelete();
            $table->string('accession_no', 50);                       // the number stamped in the book
            $table->string('condition', 20)->default('good');         // good, fair, poor
            $table->string('status', 20)->default('available');       // available, on_loan, lost, withdrawn
            $table->timestamps();

            $table->unique(['school_id', 'accession_no']);
        });

        Schema::create('library_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('library_copy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->date('issued_on');
            $table->date('due_on')->nullable();
            $table->date('returned_on')->nullable();
            $table->string('return_condition', 20)->nullable();
            $table->date('lost_on')->nullable();
            $table->foreignId('student_charge_id')->nullable()->constrained()->nullOnDelete();   // the lost book billed to fees
            $table->string('issued_by')->nullable();
            $table->string('received_by')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'returned_on', 'lost_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_loans');
        Schema::dropIfExists('library_copies');
        Schema::dropIfExists('library_books');
    }
};
