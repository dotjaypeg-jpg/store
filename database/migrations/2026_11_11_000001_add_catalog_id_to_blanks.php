<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links each local blank to its master copy on Studio (see BlankSync). Skips
 * quietly on a site with no blanks table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blanks') || Schema::hasColumn('blanks', 'catalog_id')) {
            return;
        }

        Schema::table('blanks', function (Blueprint $table) {
            $table->unsignedBigInteger('catalog_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('blanks', 'catalog_id')) {
            Schema::table('blanks', function (Blueprint $table) {
                $table->dropUnique(['catalog_id']);
                $table->dropColumn('catalog_id');
            });
        }
    }
};
