<?php

namespace Chargefield\Store\Models;

use Chargefield\Store\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property int|null $variant_id
 * @property string|null $sku
 * @property string $title
 * @property string|null $variant_title
 * @property numeric-string $price
 * @property int $quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order|null $order
 * @property-read Model|null $product  the site's App\Models\Product
 */
class OrderItem extends Model
{
    protected $table = 'order_items';

    protected $guarded = [];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
    ];

    /** Memoised result of matchedProduct(): false = not yet resolved. */
    private Model|null|false $matchedProduct = false;

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Store::model('order'), 'order_id');
    }

    /** @return BelongsTo<Model, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Store::model('product'), 'product_id');
    }

    public function lineTotal(): float
    {
        return (float) $this->price * $this->quantity;
    }

    /**
     * The current catalogue product this line item refers to, or null if it no
     * longer exists. Historical (imported) items have no product_id, so we fall
     * back to matching on the title — first exactly, then with the trailing
     * " - {size/variant}" suffix stripped (Shopify names posters
     * "Title - 60x90 cm / 24x36″"). Resolved once per instance.
     */
    public function matchedProduct(): ?Model
    {
        if ($this->matchedProduct === false) {
            $this->matchedProduct = $this->resolveProduct();
        }

        return $this->matchedProduct;
    }

    /** Storefront URL for the matched product, or null if it no longer exists. */
    public function productUrl(): ?string
    {
        return $this->matchedProduct()?->url();
    }

    private function resolveProduct(): ?Model
    {
        $product = Store::model('product');

        if ($this->product_id) {
            return $product::find($this->product_id);
        }

        $title = trim((string) $this->title);
        if ($title === '') {
            return null;
        }

        $exact = $product::whereRaw('LOWER(title) = ?', [mb_strtolower($title)])->first();
        if ($exact) {
            return $exact;
        }

        $base = trim((string) preg_replace('/\s+-\s+.*$/u', '', $title));

        return ($base !== '' && $base !== $title)
            ? $product::whereRaw('LOWER(title) = ?', [mb_strtolower($base)])->first()
            : null;
    }
}
