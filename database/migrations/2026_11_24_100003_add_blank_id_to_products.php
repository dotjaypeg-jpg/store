<?php

use Chargefield\Store\Store;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a product to the blank (stock) it's printed on, so its material + care
 * come from the shared blank. Added to whatever table the site's Product model
 * uses; guarded, so it's safe where the column already exists (e.g. johngodfrey)
 * and skips quietly on a site with no products table.
 */
return new class extends Migration
{
    private function table(): ?string
    {
        $class = Store::model('product');
        if (! $class || ! class_exists($class)) {
            return null;
        }

        return (new $class)->getTable();
    }

    public function up(): void
    {
        $table = $this->table();
        if (! $table || ! Schema::hasTable($table) || Schema::hasColumn($table, 'blank_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $t) {
            $t->unsignedBigInteger('blank_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        $table = $this->table();
        if ($table && Schema::hasColumn($table, 'blank_id')) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('blank_id');
            });
        }
    }
};
