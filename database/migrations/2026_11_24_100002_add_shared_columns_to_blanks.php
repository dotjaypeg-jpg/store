<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * For a site that already had its own `blanks` table: add any of the shared
 * columns it's missing (material blurb, care, size guide, is_default). Each is
 * guarded, so this is safe on a site that already has them (e.g. johngodfrey)
 * and on the fresh table the companion migration just created.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blanks')) {
            return;
        }

        Schema::table('blanks', function (Blueprint $table) {
            if (! Schema::hasColumn('blanks', 'is_default')) {
                $table->boolean('is_default')->default(false);
            }
            if (! Schema::hasColumn('blanks', 'blurb')) {
                $table->text('blurb')->nullable();
            }
            if (! Schema::hasColumn('blanks', 'care')) {
                $table->json('care')->nullable();
            }
            if (! Schema::hasColumn('blanks', 'size_guide')) {
                $table->json('size_guide')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Non-destructive: keep the columns on rollback.
    }
};
