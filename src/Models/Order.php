<?php

namespace Chargefield\Store\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Chargefield\Store\Sales\ReportsToStudio;
use Chargefield\Store\Sales\SendsSalesToStudio;
use Chargefield\Store\Store;
use Illuminate\Support\Str;

/**
 * Shared store order. Sites extend this as App\Models\Order.
 *
 * @property int $id
 * @property string $order_number
 * @property string $source
 * @property string|null $external_id
 * @property string|null $email
 * @property bool $newsletter_opt_in
 * @property string|null $customer_name
 * @property string $status
 * @property string $currency
 * @property numeric-string $subtotal
 * @property numeric-string $shipping_total
 * @property numeric-string $discount_total
 * @property numeric-string $tax_total
 * @property numeric-string $refunded_total
 * @property numeric-string $total
 * @property string|null $stripe_session_id
 * @property string|null $stripe_payment_intent
 * @property array<string, mixed>|null $shipping_address
 * @property Carbon|null $placed_at
 * @property Carbon|null $review_request_sent_at
 * @property Carbon|null $pii_redacted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, OrderItem> $items
 * @property-read Collection<int, OrderFulfillment> $fulfillments
 */
class Order extends Model implements ReportsToStudio
{
    use SendsSalesToStudio;

    /** Statuses that mean no money has come in yet: nothing to tell Studio. */
    public const UNPAID_STATUSES = ['pending', 'open', 'draft', 'expired', 'abandoned'];

    protected $table = 'orders';

    protected $guarded = [];

    protected $casts = [
        'shipping_address' => 'array',
        'subtotal' => 'decimal:2',
        'shipping_total' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'refunded_total' => 'decimal:2',
        'total' => 'decimal:2',
        'newsletter_opt_in' => 'boolean',
        'placed_at' => 'datetime',
        'review_request_sent_at' => 'datetime',
        'pii_redacted_at' => 'datetime',
    ];

    /** True once the order's personal data has been reduced to a low-risk token. */
    public function isPiiReduced(): bool
    {
        return $this->pii_redacted_at !== null;
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->order_number ??= config('store.order_number_prefix', 'JG-').strtoupper(Str::random(8));
        });
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(Store::model('order_item'), 'order_id');
    }

    /** @return HasMany<OrderFulfillment, $this> */
    public function fulfillments(): HasMany
    {
        return $this->hasMany(Store::model('order_fulfillment'), 'order_id');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * A genuinely live checkout (a `cs_live_` Stripe session), as opposed to a test
     * checkout. Imported history (e.g. Shopify) is neither.
     */
    public function isLiveCheckout(): bool
    {
        return str_starts_with((string) $this->stripe_session_id, 'cs_live_');
    }

    /** @return array<string, mixed>|null */
    public function studioSale(bool $includeImported = false): ?array
    {
        $imported = ! $this->isLiveCheckout() && $this->source !== 'store';
        if (! $this->isLiveCheckout() && ! ($includeImported && $imported)) {
            return null;
        }
        if (in_array($this->status, self::UNPAID_STATUSES, true) || $this->status === null) {
            return null;
        }

        $total = (int) round((float) $this->total * 100);
        $refunded = (int) round((float) $this->refunded_total * 100);
        $received = max(0, $total - $refunded);
        $tax = (int) round((float) $this->tax_total * 100);
        // A partial refund gives back its share of the tax too.
        $taxKept = $total > 0 ? (int) round($tax * $received / $total) : 0;

        $address = (array) ($this->shipping_address ?? []);
        $country = $address['address']['country'] ?? $address['country'] ?? null;
        $units = (int) $this->items()->sum('quantity');

        return [
            'ref' => $this->order_number,
            'kind' => 'store',
            'status' => in_array($this->status, ['refunded', 'cancelled'], true) ? $this->status : 'paid',
            'occurred_on' => ($this->placed_at ?? $this->created_at ?? now())->toDateString(),
            'currency' => strtoupper($this->currency ?: (string) config('store.currency', 'CAD')),
            'amount_cents' => $received,
            'tax_cents' => $taxKept,
            'country' => is_string($country) ? strtoupper($country) : null,
            'party' => null,
            'description' => $units > 0 ? $units.' item'.($units === 1 ? '' : 's') : null,
        ];
    }

    /** @return array<int, string> */
    public function studioSaleWatched(): array
    {
        return ['status', 'total', 'tax_total', 'refunded_total', 'placed_at', 'stripe_session_id'];
    }

    public function markPaid(?string $paymentIntent = null): void
    {
        $this->forceFill([
            'status' => 'paid',
            'stripe_payment_intent' => $paymentIntent ?? $this->stripe_payment_intent,
        ])->save();
    }
}
