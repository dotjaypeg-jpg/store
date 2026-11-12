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
 *   $vault->store($commission, $tmp, $upload->getClientOriginalName(), 'onesheet',
 *       dir: "commission-{$commission->id}", name: 'poster-dune-2021-onesheet');
 *
 *   $vault->putBytes($work, $bytes, 'poster', "masters/{$work->slug}.png", ['dpi' => 300]);
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
     * Store an uploaded (or local) file as a master of $owner. `name` is the stored
     * name without its extension. With `replace`, it becomes THE master for the
     * role (overwriting the same name); otherwise it's added alongside, and a
     * clashing name gets -2, -3…
     *
     * @param  Model&HasMasters  $owner
     * @param  array<string, mixed>  $meta
     */
    public function store(Model $owner, string $sourcePath, ?string $originalName, string $role, string $dir, string $name, bool $replace = false, array $meta = []): MasterFile
    {
        $ext = self::guessExtension($sourcePath, $originalName);
        $name = Str::slug($name) ?: 'master';
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
     * Write bytes to an exact path and make them THE master for the role (for
     * masters made in code: ingests, downscaled print copies).
     *
     * @param  Model&HasMasters  $owner
     * @param  array<string, mixed>  $meta
     */
    public function putBytes(Model $owner, string $bytes, string $role, string $path, array $meta = [], ?string $originalName = null): MasterFile
    {
        $this->disk()->put($path, $bytes);

        return $owner->recordMaster($role, $path, [
            ...self::factsFromBytes($bytes),
            'original_filename' => $originalName,
            'meta' => $meta ?: null,
        ]);
    }

    public function disk(): Filesystem
    {
        return Storage::disk(Masters::disk());
    }
}
