<?php

namespace Chargefield\Store\Console;

use Chargefield\Store\Masters\Masters;
use Chargefield\Store\Masters\MasterFile;
use Illuminate\Console\Command;

/**
 * Check every master in `master_files` is really on its disk, and fill in the
 * size and sha256 where a row doesn't have them yet (rows moved over from the
 * old columns). Backups rely on the sha256 to know what they already hold.
 */
class VerifyMasters extends Command
{
    protected $signature = 'masters:verify
        {--checksums : Read files with no sha256 yet and record it (downloads them)}';

    protected $description = 'Check every master file exists, and record missing sizes and checksums';

    public function handle(): int
    {
        $ok = 0;
        $filled = 0;
        $missing = [];

        Masters::model()::query()->orderBy('id')->chunkById(200, function ($files) use (&$ok, &$filled, &$missing) {
            /** @var MasterFile $file */
            foreach ($files as $file) {
                $disk = $file->storage();

                if (! $disk->exists($file->path)) {
                    $missing[] = [$file->id, $file->owner_type.' #'.$file->owner_id, $file->role, $file->path];

                    continue;
                }
                $ok++;

                $changes = [];
                if ($file->bytes <= 0) {
                    $changes['bytes'] = (int) $disk->size($file->path);
                }
                if ($file->checksum === null && $this->option('checksums')) {
                    $changes['checksum'] = $this->sha256($file);
                }

                $changes = array_filter($changes, fn ($v) => $v !== null);
                if ($changes !== []) {
                    $file->forceFill($changes)->saveQuietly();
                    $filled++;
                }
            }
        });

        $this->info("{$ok} master(s) on disk, {$filled} updated with size or checksum.");

        if ($missing !== []) {
            $this->error(count($missing).' master(s) missing from their disk:');
            $this->table(['id', 'owner', 'role', 'path'], $missing);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function sha256(MasterFile $file): ?string
    {
        $stream = $file->storage()->readStream($file->path);
        if (! is_resource($stream)) {
            return null;
        }

        $ctx = hash_init('sha256');
        hash_update_stream($ctx, $stream);
        fclose($stream);

        return hash_final($ctx);
    }
}
