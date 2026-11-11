<?php

namespace Chargefield\Store\Catalog;

use Chargefield\Store\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Keeps this store's `blanks` table in step with Studio's master blanks.
 *
 *  1. Offer: local blanks with no `catalog_id` (made here, or from before the
 *     catalogue existed) are sent to Studio, which adopts a same-named one or
 *     creates it, and answers with its id.
 *  2. Pull: every master blank is written into the local row with that
 *     `catalog_id`, so Studio's copy wins. Local ids never change, so
 *     products.blank_id links stay put.
 *
 * Blanks deleted on Studio are left here untouched (products may still use them).
 */
class BlankSync
{
    public static function enabled(): bool
    {
        return filled(config('store.catalog.url')) && filled(config('store.catalog.secret'));
    }

    /** Studio's edit page for a master blank. */
    public static function editUrl(int $catalogId): string
    {
        return rtrim((string) config('store.catalog.url'), '/')."/admin/blanks/{$catalogId}/edit";
    }

    /** @return array{offered:int, created:int, updated:int, unchanged:int} */
    public function sync(): array
    {
        if (! self::enabled()) {
            throw new RuntimeException('The shared catalogue is not configured (STORE_CATALOG_URL / STORE_CATALOG_SECRET).');
        }

        $stats = ['offered' => $this->offer(), 'created' => 0, 'updated' => 0, 'unchanged' => 0];

        $master = $this->client()->get('/api/catalog/blanks')->throw()->json('blanks', []);

        // Non-defaults first, so saving a default last can't be undone by a later row.
        usort($master, fn ($a, $b) => (int) ! empty($a['is_default']) <=> (int) ! empty($b['is_default']));

        $class = Store::model('blank');

        foreach ($master as $row) {
            /** @var Model $blank */
            $blank = $class::query()->where('catalog_id', $row['id'])->first() ?? new $class;
            $isNew = ! $blank->exists;

            $blank->forceFill([
                'catalog_id' => $row['id'],
                'name' => $row['name'],
                'category' => $row['category'],
                'is_default' => (bool) ($row['is_default'] ?? false),
                'blurb' => $row['blurb'] ?? null,
                'care' => $row['care'] ?? null,
            ]);
            // Through the site's mutator, which reparses size_guide from the source;
            // a guide with no source (hand-built) is copied as-is.
            $blank->size_guide_source = $row['size_guide_source'] ?? null;
            if (blank($row['size_guide_source'] ?? null)) {
                $blank->size_guide = $row['size_guide'] ?? null;
            }

            if ($isNew) {
                $blank->save();
                $stats['created']++;
            } elseif ($blank->isDirty()) {
                $blank->save();
                $stats['updated']++;
            } else {
                $stats['unchanged']++;
            }
        }

        return $stats;
    }

    /** Send Studio the local blanks it doesn't link to yet; store the ids it hands back. */
    private function offer(): int
    {
        $class = Store::model('blank');
        $unlinked = $class::query()->whereNull('catalog_id')->orderBy('id')->get();

        if ($unlinked->isEmpty()) {
            return 0;
        }

        $ids = $this->client()->post('/api/catalog/blanks/offer', [
            'blanks' => $unlinked->map(fn (Model $b) => [
                'name' => $b->getAttribute('name'),
                'category' => $b->getAttribute('category'),
                'is_default' => (bool) $b->getAttribute('is_default'),
                'blurb' => $b->getAttribute('blurb'),
                'care' => $b->getAttribute('care'),
                'size_guide_source' => $b->getAttribute('size_guide_source'),
            ])->values()->all(),
        ])->throw()->json('ids', []);

        foreach ($unlinked->values() as $i => $blank) {
            if (! isset($ids[$i])) {
                continue;
            }
            // Two local rows can map to one master blank (same name twice): only the
            // first takes the link, the other stays local rather than break the unique key.
            if ($class::query()->where('catalog_id', $ids[$i])->exists()) {
                continue;
            }
            $blank->forceFill(['catalog_id' => $ids[$i]])->saveQuietly();
        }

        return $unlinked->count();
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('store.catalog.url'), '/'))
            ->withToken((string) config('store.catalog.secret'))
            ->acceptJson()
            ->timeout(20);
    }
}
