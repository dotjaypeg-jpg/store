<?php

namespace Chargefield\Store\Studio;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Studio PM's clients, as every site sees them. Studio holds the one client list;
 * a site links its own records to it by storing `studio_client_id` (see
 * {@see LinksStudioClient}). Only business names cross over, never contact
 * details.
 *
 * Reads `GET {store.catalog.url}/api/catalog/clients` with the catalogue secret,
 * cached for five minutes. Unconfigured or unreachable = an empty list, so an
 * admin form never breaks.
 */
class StudioClients
{
    private const CACHE_KEY = 'store.studio.clients';

    public static function enabled(): bool
    {
        return filled(config('store.catalog.url')) && filled(config('store.catalog.secret'));
    }

    /** @return array<int, array{id:int, name:string, parent_id:int|null}> keyed by id */
    public static function all(): array
    {
        if (! self::enabled()) {
            return [];
        }

        return Cache::remember(self::CACHE_KEY, 300, function () {
            try {
                $res = Http::timeout(8)
                    ->withToken((string) config('store.catalog.secret'))
                    ->acceptJson()
                    ->get(rtrim((string) config('store.catalog.url'), '/').'/api/catalog/clients');

                if (! $res->successful()) {
                    Log::warning("Studio clients fetch failed ({$res->status()}).");

                    return [];
                }

                $out = [];
                foreach ((array) $res->json('clients', []) as $row) {
                    if (! isset($row['id']) || blank($row['name'] ?? null)) {
                        continue;
                    }
                    $out[(int) $row['id']] = [
                        'id' => (int) $row['id'],
                        'name' => (string) $row['name'],
                        'parent_id' => isset($row['parent_id']) ? (int) $row['parent_id'] : null,
                    ];
                }

                return $out;
            } catch (\Throwable $e) {
                Log::warning('Studio clients fetch failed: '.$e->getMessage());

                return [];
            }
        });
    }

    /** @return array<int, string> id => name, for a Select. */
    public static function options(): array
    {
        return array_map(fn (array $c) => $c['name'], self::all());
    }

    public static function name(?int $id): ?string
    {
        return $id ? (self::all()[$id]['name'] ?? null) : null;
    }

    /** Studio's admin page for a client. */
    public static function url(int $id): string
    {
        return rtrim((string) config('store.catalog.url'), '/')."/admin/clients/{$id}";
    }

    /** The Studio client whose name matches, or null when none (or several) do. */
    public static function match(?string $name): ?int
    {
        $key = self::normalize($name);
        if ($key === '') {
            return null;
        }

        $hits = array_keys(array_filter(self::all(), fn (array $c) => self::normalize($c['name']) === $key));

        return count($hits) === 1 ? $hits[0] : null;
    }

    /**
     * Lowercase, drop legal suffixes and punctuation, so spelling variants of one
     * company match ("Acme Inc." = "Acme, LLC"). Conservative on purpose: industry
     * words (Films, Pictures, Media) stay, since "Star Films" and "Star Media" are
     * likely different companies. Same rule as chargefield's Client::normalize().
     */
    public static function normalize(?string $name): string
    {
        $n = Str::lower(trim((string) $name));
        $n = (string) preg_replace('/\b(inc|llc|l\.?l\.?c|ltd|limited|corp|corporation|co|company|gmbh|plc|productions?)\b\.?/i', '', $n);

        return trim((string) preg_replace('/[^a-z0-9]+/i', ' ', $n));
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
