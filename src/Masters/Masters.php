<?php

namespace Chargefield\Store\Masters;

/**
 * Where masters live, and the disk settings every site shares.
 *
 * Call diskConfig() and backupsConfig() from config/filesystems.php only (they
 * read env, which is empty once config is cached):
 *
 *   'masters' => \Chargefield\Store\Masters\Masters::diskConfig(),
 *   'backups' => \Chargefield\Store\Masters\Masters::backupsConfig(),
 *
 * The same env names on every site:
 *
 *   MASTERS_BUCKET, MASTERS_ACCESS_KEY_ID, MASTERS_SECRET_ACCESS_KEY,
 *   MASTERS_DEFAULT_REGION, MASTERS_ENDPOINT, MASTERS_USE_PATH_STYLE_ENDPOINT
 *   BACKUP_BUCKET,  BACKUP_ACCESS_KEY_ID,  BACKUP_SECRET_ACCESS_KEY,
 *   BACKUP_DEFAULT_REGION,  BACKUP_ENDPOINT,  BACKUP_USE_PATH_STYLE_ENDPOINT
 *
 * No bucket means a local disk under storage/app (dev). The older chargefield
 * names (MASTERS_DRIVER, MASTERS_KEY, MASTERS_SECRET, MASTERS_REGION) still
 * work as fallbacks.
 */
class Masters
{
    public static function disk(): string
    {
        return (string) config('store.masters.disk', 'masters');
    }

    /** @return class-string<MasterFile> */
    public static function model(): string
    {
        return (string) config('store.masters.model', MasterFile::class);
    }

    /** @return array<string, mixed> */
    public static function diskConfig(): array
    {
        return self::bucket('MASTERS', 'masters', aws: true, legacy: [
            'key' => ['MASTERS_KEY'],
            'secret' => ['MASTERS_SECRET'],
            'region' => ['MASTERS_REGION'],
        ]);
    }

    /**
     * Backups never borrow the AWS_* settings: they must land in their own bucket,
     * not the site's main one.
     *
     * @return array<string, mixed>
     */
    public static function backupsConfig(): array
    {
        return self::bucket('BACKUP', 'backups', aws: false);
    }

    /**
     * A private R2/S3 bucket when {PREFIX}_BUCKET is set, else a local folder.
     * No 'visibility': R2 has no per-object ACLs and rejects writes that set one;
     * the bucket itself is private.
     *
     * @param  bool  $aws  fall back to the AWS_* settings when ours are unset
     * @param  array<string, list<string>>  $legacy  older env names to fall back to
     * @return array<string, mixed>
     */
    private static function bucket(string $prefix, string $folder, bool $aws, array $legacy = []): array
    {
        $env = function (string $name, string $field, mixed $default = null) use ($legacy) {
            foreach ([$name, ...($legacy[$field] ?? [])] as $key) {
                $value = env($key);
                if ($value !== null && $value !== '') {
                    return $value;
                }
            }

            return $default;
        };

        $bucket = env("{$prefix}_BUCKET");
        $driver = env("{$prefix}_DRIVER") ?: ($bucket ? 's3' : 'local');

        if ($driver !== 's3') {
            return [
                'driver' => 'local',
                'root' => storage_path("app/{$folder}"),
                'serve' => false,
                'throw' => false,
                'report' => false,
            ];
        }

        return [
            'driver' => 's3',
            'key' => $env("{$prefix}_ACCESS_KEY_ID", 'key', $aws ? env('AWS_ACCESS_KEY_ID') : null),
            'secret' => $env("{$prefix}_SECRET_ACCESS_KEY", 'secret', $aws ? env('AWS_SECRET_ACCESS_KEY') : null),
            'region' => $env("{$prefix}_DEFAULT_REGION", 'region', env('AWS_DEFAULT_REGION', 'auto')),
            'bucket' => $bucket,
            'endpoint' => $env("{$prefix}_ENDPOINT", 'endpoint', $aws ? env('AWS_ENDPOINT') : null),
            'use_path_style_endpoint' => (bool) env("{$prefix}_USE_PATH_STYLE_ENDPOINT", false),
            'throw' => false,
            'report' => false,
        ];
    }
}
