<?php

namespace Chargefield\Store\Masters;

use Chargefield\Store\Sales\StudioSales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

/**
 * Reports this site's masters to Studio's masters library (POST
 * /api/catalog/masters): one row per file, with what it is (title, client,
 * year, role, category), its size, pixels and checksum, and where to look at
 * it. The files never leave; Studio asks for a signed link when someone
 * downloads one (GET /api/store/master-file/{id}).
 *
 * Runs hourly with the catalogue set up and a store key (store.sales.store).
 */
class MasterReport
{
    private const CHUNK = 200;

    public static function enabled(): bool
    {
        return StudioSales::enabled();
    }

    /** @return array<string, mixed> */
    public static function snapshot(MasterFile $file): array
    {
        $owner = $file->owner;
        $naming = $owner instanceof Model && method_exists($owner, 'masterNaming') ? $owner->masterNaming() : [];
        $details = $owner instanceof Model && method_exists($owner, 'masterIndexDetails') ? $owner->masterIndexDetails() : [];

        return [
            'id' => $file->id,
            'role' => $file->role,
            'category' => $file->category(),
            'owner_type' => $owner ? class_basename($owner) : class_basename((string) $file->owner_type),
            'owner_id' => $file->owner_id,
            'title' => filled($naming['title'] ?? null) ? (string) $naming['title'] : null,
            'client' => filled($naming['client'] ?? null) ? (string) $naming['client'] : null,
            'year' => filled($naming['year'] ?? null) ? (int) $naming['year'] : null,
            'studio_client_id' => $details['studio_client_id'] ?? null,
            'path' => $file->path,
            'original_filename' => $file->original_filename,
            'mime' => $file->mime,
            'bytes' => (int) $file->bytes,
            'checksum' => $file->checksum,
            'width' => $file->width,
            'height' => $file->height,
            'preview_url' => $details['preview_url'] ?? null,
            'admin_url' => $details['admin_url'] ?? null,
            'url' => $details['url'] ?? null,
            'updated_at' => $file->updated_at?->toIso8601String(),
        ];
    }

    /** @return array{sent:int, removed:int} */
    public function report(): array
    {
        $class = Masters::model();
        $ids = [];
        $batches = [];

        $class::query()->with('owner')->orderBy('id')->chunk(self::CHUNK, function ($files) use (&$batches, &$ids) {
            $batch = [];
            foreach ($files as $file) {
                $batch[] = self::snapshot($file);
                $ids[] = (int) $file->getKey();
            }
            $batches[] = $batch;
        });

        // Always at least one call, so a site with no masters still clears Studio's list.
        $batches = $batches ?: [[]];
        $last = count($batches) - 1;
        $sent = 0;
        $removed = 0;

        foreach ($batches as $i => $batch) {
            $payload = ['store' => StudioSales::storeKey(), 'base_url' => url('/'), 'masters' => $batch];
            if ($i === $last) {
                $payload['present_ids'] = $ids;
            }

            $res = Http::baseUrl(rtrim((string) config('store.catalog.url'), '/'))
                ->withToken((string) config('store.catalog.secret'))
                ->acceptJson()
                ->timeout(60)
                ->post('/api/catalog/masters', $payload)
                ->throw()
                ->json();

            $sent += (int) ($res['saved'] ?? 0);
            $removed += (int) ($res['removed'] ?? 0);
        }

        return ['sent' => $sent, 'removed' => $removed];
    }
}
