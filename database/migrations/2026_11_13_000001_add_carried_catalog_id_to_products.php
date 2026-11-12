<?php

use Chargefield\Store\Store;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a product as a copy of one carried from another store: Studio's id for
 * the original (see Catalog\Carrying). Null for the store's own products.
 * Skips quietly on a site with no products table.
 */
return new class extends Migration
{
    private function table(): string
    {
        $class = Store::model('product');

        return (new $class)->getTable();
    }

    public function up(): void
    {
        $table = $this->table();
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'carried_catalog_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $t) {
            $t->unsignedBigInteger('carried_catalog_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        $table = $this->table();
        if (Schema::hasColumn($table, 'carried_catalog_id')) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropUnique(['carried_catalog_id']);
                $t->dropColumn('carried_catalog_id');
            });
        }
    }
};
