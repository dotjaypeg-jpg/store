<?php

namespace Chargefield\Store\Console;

use Chargefield\Store\Masters\MasterReport;
use Illuminate\Console\Command;

class ReportMasters extends Command
{
    protected $signature = 'store:report-masters';

    protected $description = 'Send this site\'s masters to Studio\'s masters library';

    public function handle(MasterReport $report): int
    {
        if (! MasterReport::enabled()) {
            $this->info('Masters reporting not configured (needs the catalogue URL + secret and store.sales.store).');

            return self::SUCCESS;
        }

        $r = $report->report();
        $this->info("Masters: {$r['sent']} sent, {$r['removed']} removed from Studio.");

        return self::SUCCESS;
    }
}
