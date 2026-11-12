<?php

namespace Chargefield\Store\Masters;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * One private master file on the masters disk. Never publicly addressable:
 * reach it through a signed temporary URL or a gated download route.
 *
 * Deleting the row deletes the object, unless another row points at the same
 * file. Replacing a master (HasMasters::recordMaster) never deletes anything.
 *
 * @property int $id
 * @property string $owner_type
 * @property int $owner_id
 * @property string $role
 * @property string $disk
 * @property string $path
 * @property string|null $original_filename
 * @property string|null $mime
 * @property int $bytes
 * @property string|null $checksum
 * @property int|null $width
 * @property int|null $height
 * @property array<string, mixed>|null $meta
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $owner
 */
class MasterFile extends Model
{
    protected $table = 'master_files';

    protected $guarded = [];

    protected $casts = [
        'bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'position' => 'integer',
        'meta' => 'array',
    ];

    /** What a master can be for. Sites may use others; these are the shared names. */
    public const ROLES = [
        'poster' => 'Poster (full artwork)',
        'apparel' => 'Apparel design',
        'apparel_print' => 'Apparel print copy',
        'onesheet' => 'One-sheet',
        'teaser' => 'Teaser',
        'alt' => 'Alternate',
        'character' => 'Character',
        'festival' => 'Festival',
    ];

    protected static function booted(): void
    {
        static::deleted(function (self $master) {
            $shared = static::query()
                ->where('disk', $master->disk)
                ->where('path', $master->path)
                ->exists();

            if (! $shared) {
                $master->storage()->delete($master->path);
            }
        });
    }

    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function storage(): Filesystem
    {
        return Storage::disk($this->disk ?: Masters::disk());
    }

    /**
     * A short-lived signed URL, for a print provider or a download. Null when the
     * disk can't sign (the local dev disk): callers treat that as "no real file".
     */
    public function temporaryUrl(int $ttlMinutes = 60): ?string
    {
        try {
            return $this->storage()->temporaryUrl($this->path, now()->addMinutes($ttlMinutes));
        } catch (\Throwable) {
            return null;
        }
    }

    /** Roles that are always filed under apparel, whatever the owner is. */
    public const APPAREL_ROLES = ['apparel', 'apparel_print'];

    /** The library category: apparel for garment files, else the owner's, else other. */
    public function category(): string
    {
        if (in_array($this->role, self::APPAREL_ROLES, true)) {
            return 'apparel';
        }

        $owner = $this->owner;
        $category = $owner && method_exists($owner, 'masterCategory') ? $owner->masterCategory() : null;

        return MasterCategories::valid($category) ? $category : 'other';
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? ucfirst(str_replace('_', ' ', $this->role));
    }

    public function humanBytes(): string
    {
        $b = (float) $this->bytes;
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($b < 1024 || $unit === 'GB') {
                return round($b, $unit === 'B' ? 0 : 1).' '.$unit;
            }
            $b /= 1024;
        }

        return $this->bytes.' B';
    }
}
