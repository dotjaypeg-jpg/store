<?php

namespace Chargefield\Store\Catalog;

use Chargefield\Store\Sales\StudioSales;
use Chargefield\Store\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

/**
 * Reports this store's products to Studio's product index (POST
 * /api/catalog/products). Products are still edited here; Studio keeps one list
 * across every store and ties each store's sale lines to it.
 *
 * The site says what to send through `store.catalog.product_snapshot`, a
 * callable returning, for one product:
 *   id, title, handle, category, status, blank_catalog_id, provider, currency,
 *   price_min_cents, price_max_cents, variants [{id, title, sku, price_cents}],
 *   url, admin_url, image_url
 * Files under the store key the sales use (store.sales.store).
 */
class ProductReport
{
    private const CHUNK = 100;

    public static function enabled(): bool
    {
        return StudioSales::enabled() && filled(config('store.catalog.product_snapshot'));
    }

    /** @return array<string, mixed> */
    public static function snapshot(Model $product): array
    {
        return (array) call_user_func(config('store.catalog.product_snapshot'), $product);
    }

    /** @return array{sent:int, removed:int} */
    public function report(): array
    {
        $class = Store::model('product');
        $ids = [];
        $sent = 0;
        $removed = 0;

        $batches = [];
        $class::query()->orderBy('id')->chunk(self::CHUNK, function ($products) use (&$batches, &$ids) {
            $batch = [];
            foreach ($products as $product) {
                $batch[] = self::snapshot($product);
                $ids[] = (int) $product->getKey();
            }
            $batches[] = $batch;
        });

        // Always at least one call, so an empty store still clears Studio's list.
        $batches = $batches ?: [[]];
        $last = count($batches) - 1;

        foreach ($batches as $i => $batch) {
            $payload = ['store' => StudioSales::storeKey(), 'products' => $batch];
            if ($i === $last) {
                $payload['present_ids'] = $ids;
            }

            $res = Http::baseUrl(rtrim((string) config('store.catalog.url'), '/'))
                ->withToken((string) config('store.catalog.secret'))
                ->acceptJson()
                ->timeout(30)
                ->post('/api/catalog/products', $payload)
                ->throw()
                ->json();

            $sent += (int) ($res['saved'] ?? 0);
            $removed += (int) ($res['removed'] ?? 0);
        }

        return ['sent' => $sent, 'removed' => $removed];
    }
}
