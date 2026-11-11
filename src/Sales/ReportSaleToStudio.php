<?php

namespace Chargefield\Store\Sales;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Sends one sale to Studio. Retries with backoff if Studio is unreachable; the
 * nightly `store:report-sales` catches anything that still slips through.
 */
class ReportSaleToStudio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    /** @param  class-string  $class */
    public function __construct(public string $class, public int|string $id) {}

    public function handle(StudioSales $studio): void
    {
        if (! StudioSales::enabled()) {
            return;
        }

        $model = $this->class::query()->find($this->id);
        if (! $model instanceof ReportsToStudio) {
            return;
        }

        $sale = $model->studioSale();
        if ($sale === null) {
            return;
        }

        $studio->send([['store' => StudioSales::storeKey()] + $sale]);
    }
}
