<?php

namespace Chargefield\Store\Console;

use Chargefield\Store\Studio\StudioClients;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Links unlinked records to Studio clients by name. Each model listed in
 * `store.studio_clients.models` (using LinksStudioClient) with no
 * studio_client_id and a filled name column is matched against Studio's list;
 * only a single exact match (after normalising) is taken. Dry run by default.
 */
class LinkStudioClients extends Command
{
    protected $signature = 'studio:link-clients {--write : Save the matches (default: dry run)}';

    protected $description = 'Link records to Studio PM clients by matching their client names';

    public function handle(): int
    {
        if (! StudioClients::enabled()) {
            $this->error('Studio is not configured (STORE_CATALOG_URL / STORE_CATALOG_SECRET).');

            return self::FAILURE;
        }

        StudioClients::forget();
        if (StudioClients::all() === []) {
            $this->error('Studio returned no clients.');

            return self::FAILURE;
        }

        $write = (bool) $this->option('write');

        foreach ((array) config('store.studio_clients.models', []) as $class) {
            /** @var class-string<Model> $class */
            $column = $class::studioClientNameColumn();
            $linked = 0;
            $unmatched = [];

            $class::query()
                ->whereNull('studio_client_id')
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->orderBy((new $class)->getKeyName())
                ->each(function (Model $row) use ($column, $write, &$linked, &$unmatched) {
                    $name = (string) $row->getAttribute($column);
                    $id = StudioClients::match($name);

                    if ($id === null) {
                        $unmatched[$name] = true;

                        return;
                    }

                    $linked++;
                    $this->line("  {$name} → ".StudioClients::name($id)." (#{$id})");
                    if ($write) {
                        $row->forceFill(['studio_client_id' => $id])->saveQuietly();
                    }
                });

            $this->info(class_basename($class).": {$linked} ".($write ? 'linked' : 'to link').', '.count($unmatched).' names with no single match.');
            foreach (array_keys($unmatched) as $name) {
                $this->line("  ? {$name}");
            }
        }

        if (! $write) {
            $this->comment('Dry run. Re-run with --write to save.');
        }

        return self::SUCCESS;
    }
}
