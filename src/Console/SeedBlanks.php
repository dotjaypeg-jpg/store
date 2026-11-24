<?php

namespace Chargefield\Store\Console;

use Chargefield\Store\Catalog\StandardBlanks;
use Illuminate\Console\Command;

class SeedBlanks extends Command
{
    protected $signature = 'store:seed-blanks';

    protected $description = 'Install/update the canonical shared stock blanks (material + care + size guide) for this site';

    public function handle(): int
    {
        $result = StandardBlanks::install();

        $this->info("Standard blanks: {$result['created']} created, {$result['updated']} updated.");

        return self::SUCCESS;
    }
}
