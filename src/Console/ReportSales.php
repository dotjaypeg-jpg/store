<?php

namespace Chargefield\Store\Console;

use Chargefield\Store\Sales\ReportsToStudio;
use Chargefield\Store\Sales\StudioSales;
use Chargefield\Store\Store;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * (Re-)send sales to Studio. Safe to repeat: Studio replaces each order's row.
 * Runs nightly over the last few days to catch anything a queued send missed;
 * run it by hand with --since to send older history.
 */
class ReportSales extends Command
{
    protected $signature = 'store:report-sales
        {--since= : Only orders changed on or after this date (default: 3 days ago)}
        {--include-imported : Also send imported history (e.g. Shopify), which may already be in Studio from Wave}';

    protected $description = 'Send this store\'s sales to Studio\'s revenue ledger';

    public function handle(StudioSales $studio): int
    {
        if (! StudioSales::enabled()) {
            $this->info('Studio sales reporting not configured (STORE_CATALOG_URL / STORE_CATALOG_SECRET / store.sales.store).');

            return self::SUCCESS;
        }

        $since = $this->option('since') ? now()->parse((string) $this->option('since'))->startOfDay() : now()->subDays(3)->startOfDay();
        $withImported = (bool) $this->option('include-imported');

        $sales = [];
        foreach ($this->models() as $class) {
            $class::query()->where('updated_at', '>=', $since)->orderBy('id')
                ->chunkById(200, function ($rows) use (&$sales, $withImported) {
                    foreach ($rows as $row) {
                        $sale = $row instanceof ReportsToStudio ? $row->studioSale($withImported) : null;
                        if ($sale !== null) {
                            $sales[] = ['store' => StudioSales::storeKey()] + $sale;
                        }
                    }
                });
        }

        if ($sales === []) {
            $this->info('No sales to send since '.$since->toDateString().'.');

            return self::SUCCESS;
        }

        $c = $studio->send($sales);
        $this->info("Sent ".count($sales)." sale(s) to Studio: {$c['recorded']} recorded, {$c['removed']} removed.");

        return self::SUCCESS;
    }

    /** @return array<int, class-string<Model>> */
    private function models(): array
    {
        return collect((array) config('store.sales.models', ['order']))
            ->map(fn (string $m) => class_exists($m) ? $m : Store::model($m))
            ->filter(fn ($m) => is_string($m) && class_exists($m))
            ->unique()
            ->values()
            ->all();
    }
}
