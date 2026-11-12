<?php

namespace Chargefield\Store\Console;

use Chargefield\Store\Masters\MasterFile;
use Chargefield\Store\Masters\Masters;
use Chargefield\Store\Masters\MasterVault;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Moves masters saved under older names into the layout new saves use:
 *
 *   {client}/{title}-{year}/{title}-{year}-{role}.{ext}
 *
 * One file at a time: move it on its disk (a server-side copy on R2/Spaces, then
 * the old key is removed), then point every record at the new path. A file
 * already named that way, missing from the disk, or with no owner is left
 * alone and listed. Dry run by default.
 */
class RenameMasters extends Command
{
    protected $signature = 'masters:rename {--write : Move the files (default: dry run)}';

    protected $description = 'Rename master files after what they are: client, title, year and role';

    public function handle(): int
    {
        $write = (bool) $this->option('write');
        $class = Masters::model();

        $moved = 0;
        $named = 0;
        $missing = [];
        $orphans = [];
        $failed = [];
        /** @var array<string, true> $claimed  targets taken during this run */
        $claimed = [];

        /** @var \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, MasterFile>> $groups */
        $groups = $class::query()->with('owner')->orderBy('id')->get()
            ->groupBy(fn (MasterFile $f) => $f->disk."\0".$f->path);

        foreach ($groups as $files) {
            /** @var MasterFile $lead */
            $lead = $files->first(fn (MasterFile $f) => $f->owner instanceof Model && method_exists($f->owner, 'masterNaming'))
                ?? $files->first();
            $owner = $lead->owner;

            if (! $owner instanceof Model || ! method_exists($owner, 'masterNaming')) {
                $orphans[] = $lead->path;

                continue;
            }

            if (MasterVault::isNamed($owner, $lead->role, $lead->path)) {
                $named++;

                continue;
            }

            $disk = $lead->storage();
            if (! $disk->exists($lead->path)) {
                $missing[] = $lead->path;

                continue;
            }

            [$dir, $stem] = MasterVault::naming($owner, $lead->role);
            $ext = MasterVault::normalizeExt(pathinfo($lead->path, PATHINFO_EXTENSION) ?: 'bin');
            $target = "{$dir}/{$stem}.{$ext}";
            for ($i = 2; isset($claimed[$lead->disk."\0".$target]) || $disk->exists($target); $i++) {
                $target = "{$dir}/{$stem}-{$i}.{$ext}";
            }
            $claimed[$lead->disk."\0".$target] = true;

            $this->line("  {$lead->path} → {$target}");

            if (! $write) {
                $moved++;

                continue;
            }

            if (! $disk->move($lead->path, $target) || ! $disk->exists($target)) {
                $failed[] = $lead->path;

                continue;
            }

            $class::query()->where('disk', $lead->disk)->where('path', $lead->path)->update(['path' => $target]);
            $moved++;
        }

        $this->info(($write ? "Renamed {$moved}" : "Would rename {$moved}")." master(s); {$named} already named.");

        foreach (['Missing from the disk (run masters:verify)' => $missing, 'No owner' => $orphans, 'Move failed' => $failed] as $label => $paths) {
            if ($paths !== []) {
                $this->warn("{$label}:");
                foreach ($paths as $path) {
                    $this->line("  {$path}");
                }
            }
        }

        if (! $write) {
            $this->comment('Dry run. Re-run with --write to move the files.');
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
