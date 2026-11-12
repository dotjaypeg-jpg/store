<?php

namespace Chargefield\Store\Console;

use Chargefield\Store\Catalog\Carrying;
use Illuminate\Console\Command;

class SyncCarried extends Command
{
    protected $signature = 'store:sync-carried';

    protected $description = 'Pull the products this store carries from other stores (listed in Studio)';

    public function handle(Carrying $carrying): int
    {
        if (! Carrying::enabled()) {
            $this->info('Carrying not configured (needs the catalogue, store.sales.store and store.catalog.carry).');

            return self::SUCCESS;
        }

        $r = $carrying->sync();
        $this->info("Carried products: {$r['created']} new, {$r['updated']} refreshed, {$r['drafted']} back to draft.");

        return self::SUCCESS;
    }
}
