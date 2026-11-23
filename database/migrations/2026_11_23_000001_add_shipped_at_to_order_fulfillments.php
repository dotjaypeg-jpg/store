<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a fulfilment actually shipped — distinct from shipped_notified_at (when the
 * buyer was emailed). Recorded by ShipmentRecorder for Gelato, self-shipped
 * ('manual') and carrier-label shipments alike. Skips quietly on a site without
 * the table or already carrying the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_fulfillments') || Schema::hasColumn('order_fulfillments', 'shipped_at')) {
            return;
        }

        Schema::table('order_fulfillments', function (Blueprint $table) {
            $table->timestamp('shipped_at')->nullable()->after('tracking_url');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_fulfillments', 'shipped_at')) {
            Schema::table('order_fulfillments', function (Blueprint $table) {
                $table->dropColumn('shipped_at');
            });
        }
    }
};
