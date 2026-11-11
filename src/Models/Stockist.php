<?php

namespace Chargefield\Store\Models;

use Chargefield\Store\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $logo
 * @property string|null $url
 * @property bool $is_own_store
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * A site that sells through stockists (editions etc.) adds those relations
 * on its own App\Models\Stockist; the admin list shows an "Editions" count
 * when the site's model has an editions() relation.
 */
class Stockist extends Model
{
    protected $table = 'stockists';

    protected $guarded = [];

    protected $casts = [
        'is_own_store' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Stockist $stockist) {
            $stockist->slug ??= Str::slug($stockist->name);
        });
    }

    /**
     * The site's own shop as a stockist (config store.own_store), created on
     * first use. The URL is configured, not app.url, which on Laravel Cloud is
     * the internal host.
     */
    public static function ownStore(): static
    {
        $own = (array) config('store.own_store');

        return static::firstOrCreate(
            ['slug' => $own['slug']],
            ['name' => $own['name'], 'url' => $own['url'], 'is_own_store' => true],
        );
    }

    /** Public URL for the logo (committed asset or uploaded file). */
    public function logoUrl(): ?string
    {
        return Store::imageUrl($this->logo);
    }
}
