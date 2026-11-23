<?php

namespace Chargefield\Store\Contracts;

use Chargefield\Store\Models\Order;
use Chargefield\Store\Models\OrderFulfillment;

/**
 * Delivers the buyer's "your order has shipped" notice. Bound by the host app —
 * the store package records the shipment (status, tracking, shipped_at) but owns
 * no mail: the message, sender and template belong to the app. {@see
 * \Chargefield\Store\Fulfillment\ShipmentRecorder} resolves this only when the app
 * has bound it, so a site that binds nothing still records shipments, just without
 * emailing.
 */
interface ShipmentNotifier
{
    public function notifyShipped(Order $order, OrderFulfillment $fulfillment): void;
}
