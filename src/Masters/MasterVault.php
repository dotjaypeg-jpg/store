<?php

namespace Chargefield\Store\Masters;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The one way masters get saved, on every site. The uploaded filename is never
 * trusted: the stored name comes from the owner, and the extension from the
 * file's own bytes. Every save records the file's size, sha256 and pixels on its
 * `master_files` row, so a backup can tell what it already has.
 *
 * Every master is named from what it is, via the owner's masterNaming():
 *
 *   {client}/{title}-{year}/{title}-{year}-{role}.{ext}
 *   a24/dune-2021/dune-2021-onesheet.tif
 *   own-work/holy-mountain/holy-mountain-apparel.png      (no client)
 *
 *   $vault->store($commission, $tmp, $upload->getClientOriginalName(), 'onesheet');
 *   $vault->putBytes($work, $bytes, 'poster', meta: ['dpi' => 300]);
 *
 * `masters:rename` moves files saved under older names into this layout.
 */
class MasterVault
{
    /** Extensions we recognise from a file's real MIME type. */
    public const MIME_EXT = [
        'image/tiff' => 'tif',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
        'image/vnd.adobe.photoshop' => 'psd',
        'application/pdf' => 'pdf',
        'application/postscript' => 'eps',
    ];

    /** Lowercase, drop a leading dot, jpeg → jpg, tiff → tif. */
    public static function normalizeExt(string $ext): string
    {
        $ext = Str::lower(ltrim($ext, '.'));

        return match ($ext) {
            'jpeg' => 'jpg',
            'tiff' => 'tif',
            default => $ext,
        };
    }

    /** Extension from the file's own MIME, falling back to a sanitised claimed one. */
    public static function guessExtension(string $sourcePath, ?string $originalName): string
    {
        $mime = self::mimeOf($sourcePath);

        if ($mime && isset(self::MIME_EXT[$mime])) {
            return self::MIME_EXT[$mime];
        }

        $claimed = self::normalizeExt(pathinfo((string) $originalName, PATHINFO_EXTENSION) ?: 'bin');

        return preg_replace('/[^a-z0-9]/', '', $claimed) ?: 'bin';
    }

    public static function mimeOf(string $path): ?string
    {
        return is_file($path) && function_exists('mime_content_type') ? (mime_content_type($path) ?: null) : null;
    }

    /**
     * Size, sha256, MIME and pixels of a local file.
     *
     * @return array{mime: string|null, bytes: int, checksum: string|null, width: int|null, height: int|null}
     */
    public static function facts(string $sourcePath): array
    {
        $dims = @getimagesize($sourcePath) ?: [null, null];

        return [
            'mime' => self::mimeOf($sourcePath),
            'bytes' => (int) (@filesize($sourcePath) ?: 0),
            'checksum' => @hash_file('sha256', $sourcePath) ?: null,
            'width' => $dims[0] ?? null,
            'height' => $dims[1] ?? null,
        ];
    }

    /**
     * The same facts for a file already in memory.
     *
     * @return array{mime: string|null, bytes: int, checksum: string|null, width: int|null, height: int|null}
     */
    public static function factsFromBytes(string $bytes): array
    {
        $dims = $bytes !== '' ? (@getimagesizefromstring($bytes) ?: [null, null]) : [null, null];
        $mime = class_exists(\finfo::class) ? ((new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: null) : null;

        return [
            'mime' => $mime,
            'bytes' => strlen($bytes),
            'checksum' => hash('sha256', $bytes),
            'width' => $dims[0] ?? null,
            'height' => $dims[1] ?? null,
        ];
    }

    /** The folder for masters with no client (a site's own work). */
    public const NO_CLIENT = 'own-work';

    /**
     * Folder and file stem for one of $owner's masters:
     * ['a24/dune-2021', 'dune-2021-onesheet'].
     *
     * @param  Model&HasMasters  $owner
     * @return array{0: string, 1: string}
     */
    public static function naming(Model $owner, string $role): array
    {
        $n = $owner->masterNaming();
        $title = Str::slug((string) ($n['title'] ?? '')) ?: Str::slug(class_basename($owner)).'-'.$owner->getKey();
        $year = (string) ($n['year'] ?? '');
        $piece = $year !== '' && ! str_ends_with($title, "-{$year}") ? "{$title}-{$year}" : $title;
        $client = Str::slug((string) ($n['client'] ?? '')) ?: self::NO_CLIENT;

        return ["{$client}/{$piece}", $piece.'-'.Str::slug(str_replace('_', '-', $role))];
    }

    /**
     * True when $path is already the owner's name for this role (allowing the
     * -2, -3 a clash adds), so renaming leaves it alone.
     *
     * @param  Model&HasMasters  $owner
     */
    public static function isNamed(Model $owner, string $role, string $path): bool
    {
        [$dir, $stem] = self::naming($owner, $role);

        return (bool) preg_match('#^'.preg_quote("{$dir}/{$stem}", '#').'(-\d+)?\.[a-z0-9]+$#', $path);
    }

    /** A free path: dir/name.ext, then dir/name-2.ext, -3… on collision. */
    public function uniquePath(string $dir, string $name, string $ext): string
    {
        $dir = trim($dir, '/');
        $prefix = $dir === '' ? '' : "{$dir}/";

        $candidate = "{$prefix}{$name}.{$ext}";
        $i = 1;
        while ($this->disk()->exists($candidate)) {
            $i++;
            $candidate = "{$prefix}{$name}-{$i}.{$ext}";
        }

        return $candidate;
    }

    /**
     * Store an uploaded (or local) file as a master of $owner. The folder and name
     * come from the owner (see naming()) unless given. With `replace`, it becomes
     * THE master for the role (overwriting the same name); otherwise it's added
     * alongside, and a clashing name gets -2, -3…
     *
     * @param  Model&HasMasters  $owner
     * @param  array<string, mixed>  $meta
     */
    public function store(Model $owner, string $sourcePath, ?string $originalName, string $role, ?string $dir = null, ?string $name = null, bool $replace = false, array $meta = []): MasterFile
    {
        [$namedDir, $namedStem] = self::naming($owner, $role);
        $dir ??= $namedDir;
        $ext = self::guessExtension($sourcePath, $originalName);
        $name = Str::slug($name ?? $namedStem) ?: 'master';
        $path = $replace
            ? trim(trim($dir, '/')."/{$name}.{$ext}", '/')
            : $this->uniquePath($dir, $name, $ext);

        $stream = fopen($sourcePath, 'rb');
        $this->disk()->put($path, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $attributes = [
            ...self::facts($sourcePath),
            'original_filename' => $originalName,
            'meta' => $meta ?: null,
        ];

        return $replace
            ? $owner->recordMaster($role, $path, $attributes)
            : $owner->addMaster($role, $path, $attributes);
    }

    /**
     * Write bytes as THE master for the role (for masters made in code: ingests,
     * downscaled print copies). The path comes from the owner (see naming()), with
     * the extension read from the bytes, unless one is given.
     *
     * @param  Model&HasMasters  $owner
     * @param  array<string, mixed>  $meta
     */
    public function putBytes(Model $owner, string $bytes, string $role, ?string $path = null, array $meta = [], ?string $originalName = null): MasterFile
    {
        $facts = self::factsFromBytes($bytes);

        if ($path === null) {
            [$dir, $stem] = self::naming($owner, $role);
            $claimed = self::normalizeExt(pathinfo((string) $originalName, PATHINFO_EXTENSION) ?: 'bin');
            $ext = self::MIME_EXT[$facts['mime'] ?? ''] ?? (preg_replace('/[^a-z0-9]/', '', $claimed) ?: 'bin');
            $path = "{$dir}/{$stem}.{$ext}";
        }

        $this->disk()->put($path, $bytes);

        return $owner->recordMaster($role, $path, [
            ...$facts,
            'original_filename' => $originalName,
            'meta' => $meta ?: null,
        ]);
    }

    public function disk(): Filesystem
    {
        return Storage::disk(Masters::disk());
    }
}
