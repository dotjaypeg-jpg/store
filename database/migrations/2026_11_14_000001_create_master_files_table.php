<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One table for every private master on a site, whatever owns it: a Work, a
 * Product, a Commission. `role` says what the file is for (poster, apparel,
 * apparel_print, onesheet, …). Facts about the file (size, sha256, pixels) are
 * kept on the row, so a backup or a check can work from the table alone.
 *
 * A site that already had its own `master_files` table converts it in its own
 * migration; this one only creates the table where there isn't one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('master_files')) {
            return;
        }

        Schema::create('master_files', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
            $table->string('role');
            $table->string('disk')->default('masters');
            $table->string('path');
            $table->string('original_filename')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('bytes')->default(0);
            $table->string('checksum', 64)->nullable(); // sha256
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['owner_type', 'owner_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_files');
    }
};
