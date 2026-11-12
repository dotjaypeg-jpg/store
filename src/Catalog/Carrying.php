<?php

namespace Chargefield\Store\Catalog;

use Chargefield\Store\Sales\StudioSales;
use Chargefield\Store\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Products this store carries from another store, listed in Studio.
 *
 *  - sync(): pulls this store's listings (GET /api/catalog/listings) and hands
 *    each live one to the site's `store.catalog.carry` callable, which writes
 *    the local copy (new copies land as drafts). The copy is marked with
 *    `carried_catalog_id` (Studio's id for the original). A listing that's
 *    switched off, or gone, puts its copy back to draft; nothing is deleted,
 *    since orders point at it.
 *  - printFileUrl(): at order time, asks Studio for the original's print file,
 *    which Studio fetches from the home store as a short-lived signed URL.
 *
 * The other direction, a store handing out its own print files, is
 * Http\PrintFileController.
 */
class Carrying
{
    public static function enabled(): bool
    {
        return StudioSales::enabled() && filled(config('store.catalog.carry'));
    }

    /** @return array{created:int, updated:int, drafted:int} */
    public function sync(): array
    {
        $listings = $this->client()
            ->get('/api/catalog/listings', ['store' => StudioSales::storeKey()])
            ->throw()
            ->json('listings', []);

        $class = Store::model('product');
        $stats = ['created' => 0, 'updated' => 0, 'drafted' => 0];
        $live = [];

        foreach ($listings as $listing) {
            $catalogId = (int) $listing['catalog_id'];
            /** @var Model|null $existing */
            $existing = $class::query()->where('carried_catalog_id', $catalogId)->first();

            if (empty($listing['active'])) {
                continue;
            }

            try {
                /** @var Model $product */
                $product = call_user_func(config('store.catalog.carry'), $listing, $existing);
            } catch (Throwable $e) {
                // One bad listing shouldn't stop the rest.
                report($e);
                if ($existing) {
                    $live[] = $catalogId;
                }

                continue;
            }

            if ((int) $product->getAttribute('carried_catalog_id') !== $catalogId) {
                $product->forceFill(['carried_catalog_id' => $catalogId])->save();
            }

            $live[] = $catalogId;
            $stats[$existing ? 'updated' : 'created']++;
        }

        // Every copy whose listing is off or gone goes back to draft.
        $stats['drafted'] = $class::query()
            ->whereNotNull('carried_catalog_id')
            ->whereNotIn('carried_catalog_id', $live ?: [0])
            ->where('status', '!=', 'draft')
            ->update(['status' => 'draft']);

        return $stats;
    }

    public static function isCarried(?Model $product): bool
    {
        return $product !== null && filled($product->getAttribute('carried_catalog_id'));
    }

    /**
     * The print file for a carried product, from its home store through Studio.
     * Null (and reported) when it can't be had: the order is then held back, like
     * any product without a print master.
     */
    public static function printFileUrl(Model $product): ?string
    {
        if (! self::isCarried($product) || ! StudioSales::enabled()) {
            return null;
        }

        try {
            $res = (new self)->client()->timeout(90)->post('/api/catalog/print-file', [
                'store' => StudioSales::storeKey(),
                'catalog_id' => (int) $product->getAttribute('carried_catalog_id'),
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if (! $res->successful() || blank($res->json('url'))) {
            report(new \RuntimeException(sprintf(
                'No print file for carried product %s (catalog %s): %s',
                $product->getKey(),
                $product->getAttribute('carried_catalog_id'),
                $res->json('error') ?? "HTTP {$res->status()}",
            )));

            return null;
        }

        return (string) $res->json('url');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('store.catalog.url'), '/'))
            ->withToken((string) config('store.catalog.secret'))
            ->acceptJson()
            ->timeout(30);
    }
}
