<?php

namespace Chargefield\Store\Fulfillment;

use Chargefield\Store\Contracts\ShipmentNotifier;
use Chargefield\Store\Models\Order;
use Chargefield\Store\Models\OrderFulfillment;
use Chargefield\Store\Store;

/**
 * Records that an order's fulfilment with a given provider has shipped: marks it
 * shipped, saves any tracking + shipped_at, and (once) asks the host app to email
 * the buyer. Provider-agnostic — it drives Gelato's shipped webhook, a hand-shipped
 * 'manual' order marked shipped in the admin, and a bought carrier label alike.
 *
 * Idempotent via shipped_notified_at: the buyer is emailed at most once per
 * fulfilment, so a webhook retry or a second "mark shipped" click never re-sends.
 * The upsert is keyed on (order_id, provider) — the table's unique key — so it
 * updates the existing row (e.g. the 'manual' row created at checkout) instead of
 * duplicating. The buyer email is delegated to a bound ShipmentNotifier and is
 * best-effort: a failure is reported, never thrown, and never blocks the record.
 */
class ShipmentRecorder
{
    public function record(
        Order $order,
        string $provider,
        ?string $trackingNumber = null,
        ?string $trackingUrl = null,
        bool $notify = true,
    ): OrderFulfillment {
        /** @var class-string<OrderFulfillment> $model */
        $model = Store::model('order_fulfillment');

        $fulfillment = $model::updateOrCreate(
            ['order_id' => $order->getKey(), 'provider' => $provider],
            // array_filter drops only the null tracking fields, so they never
            // overwrite tracking already saved by an earlier call.
            array_filter([
                'status' => 'shipped',
                'tracking_number' => $trackingNumber,
                'tracking_url' => $trackingUrl,
                'shipped_at' => now(),
            ], fn ($v) => $v !== null),
        );

        // Already notified, nothing to notify, or no buyer email → done. Leaving
        // shipped_notified_at unset when no notifier is bound means a site that
        // wires one up later can still send the notice on a subsequent record.
        if (! $notify
            || $fulfillment->shipped_notified_at !== null
            || blank($order->email)
            || ! app()->bound(ShipmentNotifier::class)) {
            return $fulfillment;
        }

        try {
            app(ShipmentNotifier::class)->notifyShipped($order, $fulfillment);
        } catch (\Throwable $e) {
            report($e);
        }

        // Set even if the send threw (the catch only reports) so a retry won't
        // re-attempt a failed notice — matches the prior Gelato behaviour.
        $fulfillment->forceFill(['shipped_notified_at' => now()])->save();

        return $fulfillment;
    }
}
