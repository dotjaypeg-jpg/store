<?php

namespace Chargefield\Store\Sales;

use Illuminate\Support\Facades\Http;

/**
 * Sends this store's sales to Studio (POST /api/sales), where they land in the
 * business-wide revenue ledger labelled by store and kind. Uses the catalogue's
 * Studio URL + secret, plus the key Studio files this store's sales under
 * (store.sales.store). Off unless all three are set.
 */
class StudioSales
{
    public static function enabled(): bool
    {
        return filled(config('store.catalog.url'))
            && filled(config('store.catalog.secret'))
            && filled(config('store.sales.store'));
    }

    public static function storeKey(): string
    {
        return (string) config('store.sales.store');
    }

    /**
     * @param  array<int, array<string, mixed>>  $sales  each from ReportsToStudio::studioSale()
     * @return array{recorded: int, removed: int}
     */
    public function send(array $sales): array
    {
        $counts = ['recorded' => 0, 'removed' => 0];

        foreach (array_chunk(array_values($sales), 200) as $chunk) {
            $res = Http::baseUrl(rtrim((string) config('store.catalog.url'), '/'))
                ->withToken((string) config('store.catalog.secret'))
                ->acceptJson()
                ->timeout(20)
                ->post('/api/sales', ['sales' => $chunk])
                ->throw()
                ->json();

            $counts['recorded'] += (int) ($res['recorded'] ?? 0);
            $counts['removed'] += (int) ($res['removed'] ?? 0);
        }

        return $counts;
    }
}
