<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Syncing the Windows app with the school's online account (App\Services\Sync).
 *
 *   sync_devices   online: the Windows computers linked to a school, each
 *                  with its own secret token.
 *   sync_rows      both: every synced record's shared id (uuid) and a
 *                  fingerprint of its contents. Online, `version` orders
 *                  changes for the computers to fetch; in the Windows app
 *                  the fingerprint is the record as it was at the last
 *                  sync, so what changed since is easy to find.
 *   sync_inbox     Windows app: changes fetched from online, waiting to be
 *                  saved (some wait for a decision on the review list).
 *   sync_reviews   Windows app: possible duplicates, similar payments and
 *                  deleted finance records for someone to decide on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('link_code', 64)->nullable()->unique();
            $table->timestamp('link_expires_at')->nullable();
            $table->string('token', 64)->nullable()->unique();
            $table->string('created_by')->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sync_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->index();
            $table->string('table', 64);
            $table->unsignedBigInteger('row_id');
            $table->uuid('uuid')->unique();
            $table->string('hash', 40)->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->unsignedBigInteger('source_device_id')->nullable();
            $table->string('origin', 10)->default('here');
            $table->boolean('deleted')->default(false);
            $table->boolean('delete_approved')->default(false);
            $table->timestamp('changed_at')->nullable();

            $table->index(['table', 'row_id']);
            $table->index(['school_id', 'version']);
        });

        Schema::create('sync_inbox', function (Blueprint $table) {
            $table->id();
            $table->string('table', 64);
            $table->uuid('uuid')->unique();
            $table->boolean('deleted')->default(false);
            $table->longText('data')->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->string('waiting')->nullable();
            $table->timestamps();
        });

        Schema::create('sync_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);
            $table->string('table', 64);
            $table->unsignedBigInteger('local_id')->nullable();
            $table->uuid('remote_uuid')->nullable();
            $table->longText('remote_data')->nullable();
            $table->string('status', 10)->default('open');
            $table->string('decision', 20)->nullable();
            $table->string('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'table']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_reviews');
        Schema::dropIfExists('sync_inbox');
        Schema::dropIfExists('sync_rows');
        Schema::dropIfExists('sync_devices');
    }
};
