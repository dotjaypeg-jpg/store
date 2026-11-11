<?php

namespace Chargefield\Store\Models;

use Chargefield\Store\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The "price book": a default sell price per (category, size). A row with a null size
 * is the catch-all for that category (e.g. one price for every tee), while posters get
 * a rule per size. A product variant (the site's ProductVariant) follows the matching rule unless it has its
 * own price_override.
 *
 * @property int $id
 * @property string $category
 * @property string|null $size
 * @property float|string $price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PricingRule extends Model
{
    protected $table = 'pricing_rules';

    protected $guarded = [];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    /**
     * All rules, loaded once per request (there are only a handful).
     *
     * @var Collection<int, PricingRule>|null
     */
    protected static ?Collection $book = null;

    /** @return Collection<int, PricingRule> */
    public static function book(): Collection
    {
        return static::$book ??= static::all();
    }

    /** Forget the cached book (call after editing rules). */
    public static function forgetBook(): void
    {
        static::$book = null;
    }

    /**
     * The rule that governs a given category + size: an exact size match if one exists,
     * otherwise the category's sizeless catch-all. Null when the category has no rule.
     */
    public static function resolve(?string $category, ?string $size): ?self
    {
        if (! $category) {
            return null;
        }

        $forCategory = static::book()->where('category', $category);

        return $forCategory->firstWhere('size', $size) ?? $forCategory->firstWhere('size', null);
    }

    /**
     * Variants that actually follow this rule (right category/size, no override).
     *
     * @return Builder<Model>
     */
    public function matchingVariants(): Builder
    {
        return Store::model('product_variant')::query()
            ->whereNull('price_override')
            ->whereHas('product', fn ($q) => $q->where('category', $this->category))
            ->when($this->size, fn ($q) => $q->where('options->Size', $this->size));
    }

    /** Highest provider cost among the variants this rule prices (for the margin warning). */
    public function maxCost(): ?float
    {
        $cost = $this->matchingVariants()->max('cost');

        return $cost !== null ? (float) $cost : null;
    }

    /** True when this rule's price is below what the provider charges for some variant. */
    public function isBelowCost(): bool
    {
        $cost = $this->maxCost();

        return $cost !== null && (float) $this->price < $cost;
    }

    protected static function booted(): void
    {
        // Keep the in-memory book fresh when rules change through the CMS.
        static::saved(fn () => static::forgetBook());
        static::deleted(fn () => static::forgetBook());
    }
}
