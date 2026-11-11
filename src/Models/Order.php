<?php

namespace Chargefield\Store\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
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
class Order extends Model
{
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

    public function markPaid(?string $paymentIntent = null): void
    {
        $this->forceFill([
            'status' => 'paid',
            'stripe_payment_intent' => $paymentIntent ?? $this->stripe_payment_intent,
        ])->save();
    }
}
