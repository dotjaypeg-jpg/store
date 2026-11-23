<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns for a bought shipping label (see LabelPurchaser): the carrier + service
 * used, the stored PDF's path (on the private labels disk) and format, and what
 * the label cost. Each guarded by hasColumn so the migration is safe to re-run or
 * apply to a site that already has some of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_fulfillments')) {
            return;
        }

        Schema::table('order_fulfillments', function (Blueprint $table) {
            if (! Schema::hasColumn('order_fulfillments', 'carrier')) {
                $table->string('carrier')->nullable()->after('provider');
            }
            if (! Schema::hasColumn('order_fulfillments', 'service')) {
                $table->string('service')->nullable()->after('carrier');
            }
            if (! Schema::hasColumn('order_fulfillments', 'label_path')) {
                $table->string('label_path')->nullable()->after('tracking_url');
            }
            if (! Schema::hasColumn('order_fulfillments', 'label_format')) {
                $table->string('label_format', 16)->nullable()->after('label_path');
            }
            if (! Schema::hasColumn('order_fulfillments', 'label_cost')) {
                $table->decimal('label_cost', 10, 2)->nullable()->after('label_format');
            }
            if (! Schema::hasColumn('order_fulfillments', 'label_currency')) {
                $table->string('label_currency', 3)->nullable()->after('label_cost');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_fulfillments')) {
            return;
        }

        Schema::table('order_fulfillments', function (Blueprint $table) {
            foreach (['carrier', 'service', 'label_path', 'label_format', 'label_cost', 'label_currency'] as $column) {
                if (Schema::hasColumn('order_fulfillments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
