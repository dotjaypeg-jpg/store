<?php

namespace Chargefield\Store\Models;

use Chargefield\Store\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A reusable stock definition (a specific tee / hoodie / paper stock) shared by
 * many products. Holds the material blurb, wash-care guidance and size guide
 * ONCE, so every product pointing at it — or inheriting the category default —
 * shows the same details.
 *
 * The point of sharing this in the package: the same physical blank reads the
 * same on every Chargefield site, so a site never re-authors the same boring
 * material + care copy. Install the canonical set with `store:seed-blanks`
 * (from Catalog\StandardBlanks); a site can still edit a row, and once a Studio
 * catalogue is wired, BlankSync pulls the master copy over the top.
 *
 * `catalog_id` is Studio's master blank this copies (see Catalog\BlankSync).
 *
 * Sites keep a thin subclass (App\Models\Blank extends this) so their own code,
 * factories and tests keep using App\Models\Blank. A site adds its own extras
 * (johngodfrey's size-guide parser, usage scopes) on that subclass.
 *
 * @property int $id
 * @property int|null $catalog_id
 * @property string $name
 * @property string $category
 * @property bool $is_default
 * @property string|null $blurb
 * @property array<int, array{title: string, body: string}>|null $care
 * @property array<int, array<string, string>>|null $size_guide
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Blank extends Model
{
    protected $table = 'blanks';

    protected $guarded = [];

    protected $casts = [
        'is_default' => 'boolean',
        'care' => 'array',
        'size_guide' => 'array',
    ];

    /** @var array<string, self|null> */
    protected static array $defaults = [];

    /** @return HasMany<Model, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Store::model('product'), 'blank_id');
    }

    /** The default blank for a garment/stock category (what unassigned products inherit). */
    public static function defaultFor(?string $category): ?static
    {
        if (! $category) {
            return null;
        }

        return static::$defaults[$category] ??= static::query()
            ->where('category', $category)
            ->where('is_default', true)
            ->first();
    }

    /** Forget the cached category defaults (after editing rows in bulk). */
    public static function forgetDefaults(): void
    {
        static::$defaults = [];
    }

    protected static function booted(): void
    {
        // Only one default per category — promoting one demotes the others.
        static::saved(function (self $blank) {
            if ($blank->is_default) {
                static::query()
                    ->where('category', $blank->category)
                    ->whereKeyNot($blank->getKey())
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
            static::forgetDefaults();
        });

        static::deleted(fn () => static::forgetDefaults());
    }
}
