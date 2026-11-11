<?php

namespace Chargefield\Store\Console;

use Chargefield\Store\Catalog\BlankSync;
use Illuminate\Console\Command;

class SyncBlanks extends Command
{
    protected $signature = 'store:sync-blanks';

    protected $description = 'Pull the master blanks from Studio (and offer it any blanks made here)';

    public function handle(BlankSync $sync): int
    {
        if (! BlankSync::enabled()) {
            $this->info('Shared catalogue not configured; keeping local blanks.');

            return self::SUCCESS;
        }

        $s = $sync->sync();
        $this->info("Blanks: {$s['offered']} offered, {$s['created']} new, {$s['updated']} updated, {$s['unchanged']} unchanged.");

        return self::SUCCESS;
    }
}
