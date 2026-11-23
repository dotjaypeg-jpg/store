<?php

namespace Chargefield\Store\Contracts;

use Chargefield\Store\Models\Order;
use Chargefield\Store\Models\OrderFulfillment;

/**
 * Buys a shipping label for an order and records it: stores the label PDF, saves
 * the tracking + carrier/cost on the fulfilment, and marks the order shipped.
 * Bound by the host app, which owns the carrier (via chargefield/fulfillment) and
 * the storage disk — the store package just exposes the admin action. Throws on
 * failure so the action can surface it to the admin.
 */
interface LabelPurchaser
{
    public function purchase(Order $order, ?string $provider = null): OrderFulfillment;
}
