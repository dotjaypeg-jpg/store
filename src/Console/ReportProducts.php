<?php

namespace Chargefield\Store\Console;

use Chargefield\Store\Catalog\ProductReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ReportProducts extends Command
{
    protected $signature = 'store:report-products';

    protected $description = 'Send this store\'s products to Studio\'s product index';

    private const BACKFILLED = 'store:sale-lines-backfilled';

    public function handle(ProductReport $report): int
    {
        if (! ProductReport::enabled()) {
            $this->info('Product reporting not configured (needs the catalogue, store.sales.store and store.catalog.product_snapshot).');

            return self::SUCCESS;
        }

        $r = $report->report();
        $this->info("Products: {$r['sent']} sent, {$r['removed']} marked removed on Studio.");

        // Sales sent before 1.3 went without their lines. Re-send the whole history
        // once (Studio replaces each sale's row, so nothing doubles) so the product
        // index has sales from day one.
        if (! Cache::get(self::BACKFILLED)) {
            $this->call('store:report-sales', ['--since' => '2000-01-01']);
            Cache::forever(self::BACKFILLED, now()->toIso8601String());
        }

        return self::SUCCESS;
    }
}
