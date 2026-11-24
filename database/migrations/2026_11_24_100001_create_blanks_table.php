<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shared blanks table (a reusable tee / paper stock, with its material and
 * care). Only created where a site doesn't already have one — a site that
 * brought its own blanks table (e.g. johngodfrey) keeps it; the companion
 * migration adds any missing shared columns to it instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('blanks')) {
            return;
        }

        Schema::create('blanks', function (Blueprint $table) {
            $table->id();
            // Studio's master blank this copies (see Catalog\BlankSync). Nullable +
            // unique; filled only when a Studio catalogue is configured.
            $table->unsignedBigInteger('catalog_id')->nullable()->unique();
            $table->string('name');
            $table->string('category');
            $table->boolean('is_default')->default(false);
            $table->text('blurb')->nullable();       // material / stock spec
            $table->json('care')->nullable();        // [{title, body}]
            $table->json('size_guide')->nullable();  // [{label, ...measurements}]
            $table->timestamps();

            $table->index(['category', 'is_default']);
        });
    }

    public function down(): void
    {
        // Leave a site's blanks table in place on rollback — it may predate the package.
    }
};
