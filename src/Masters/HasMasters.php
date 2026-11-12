<?php

namespace Chargefield\Store\Masters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * For any model that owns private masters (a Work, a Product, a Commission).
 * The files live in the shared `master_files` table, one row per file, with a
 * role saying what each one is for.
 */
trait HasMasters
{
    /**
     * When the owner is deleted, its master records go too (the files stay on the
     * disk: masters are never deleted as a side effect). Soft deletes keep them.
     */
    public static function bootHasMasters(): void
    {
        static::deleted(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }
            $model->masterFiles()->delete();
        });
    }

    /**
     * What this owner's master files are named from (see MasterVault::namedPath):
     *
     *   {client}/{title}-{year}/{title}-{year}-{role}.{ext}
     *
     * e.g. a24/dune-2021/dune-2021-onesheet.tif. Override per model; the default
     * reads `title` (else `name`) and `year`, with no client.
     *
     * @return array{client: string|null, title: string|null, year: int|string|null}
     */
    public function masterNaming(): array
    {
        $attributes = $this->getAttributes();

        return [
            'client' => null,
            'title' => $attributes['title'] ?? $attributes['name'] ?? null,
            'year' => $attributes['year'] ?? null,
        ];
    }

    /** @return MorphMany<MasterFile, $this> */
    public function masterFiles(): MorphMany
    {
        return $this->morphMany(Masters::model(), 'owner')->orderBy('position')->orderBy('id');
    }

    /** The current master for a role (the newest, when there are several). */
    public function masterFile(string $role): ?MasterFile
    {
        $files = $this->relationLoaded('masterFiles')
            ? $this->getRelation('masterFiles')
            : $this->masterFiles()->get();

        return $files->where('role', $role)->sortByDesc('id')->first();
    }

    public function hasMasterFile(string $role): bool
    {
        return $this->masterFile($role) !== null;
    }

    public function masterFileUrl(string $role, int $ttlMinutes = 60): ?string
    {
        return $this->masterFile($role)?->temporaryUrl($ttlMinutes);
    }

    /**
     * Set the one master for a role: updates the existing row in place (the old
     * file, if its path changed, stays on the disk; nothing is deleted here).
     *
     * @param  array<string, mixed>  $attributes  mime, bytes, checksum, width, height, meta, original_filename, disk
     */
    public function recordMaster(string $role, string $path, array $attributes = []): MasterFile
    {
        $file = $this->masterFiles()->where('role', $role)->latest('id')->first()
            ?? $this->masterFiles()->make(['role' => $role]);

        $file->fill(['disk' => Masters::disk(), ...$attributes, 'path' => $path, 'role' => $role]);
        $file->save();
        $this->unsetRelation('masterFiles');

        return $file;
    }

    /**
     * Add another master alongside any with the same role (a commission can have
     * several one-sheets).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function addMaster(string $role, string $path, array $attributes = []): MasterFile
    {
        $file = $this->masterFiles()->create(['disk' => Masters::disk(), ...$attributes, 'path' => $path, 'role' => $role]);
        $this->unsetRelation('masterFiles');

        return $file;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithMaster(Builder $query, string $role): Builder
    {
        return $query->whereHas('masterFiles', fn (Builder $q) => $q->where('role', $role));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithoutMaster(Builder $query, string $role): Builder
    {
        return $query->whereDoesntHave('masterFiles', fn (Builder $q) => $q->where('role', $role));
    }
}
