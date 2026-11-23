<?php

namespace Chargefield\Store\Mail;

use Chargefield\Store\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "You have a new order" alert, sent to the store's admins the moment an order is
 * paid — distinct from the shopper's confirmation, which goes to the buyer. Flags
 * any self-fulfilled ('manual') items, which no provider webhook moves along and
 * which the owner must print and ship by hand.
 *
 * Shared across Chargefield storefronts: the host app sends it to its admin
 * addresses after an order is paid. Delivery depends on a configured mail
 * transport. Override the look by publishing the view to
 * resources/views/vendor/chargefield-store/mail/new-order-alert.blade.php.
 */
class NewOrderAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        $total = '$'.number_format((float) $this->order->total, 2);
        $manual = $this->order->items->contains(fn ($item) => ($item->provider ?: 'gelato') === 'manual');

        return new Envelope(
            subject: ($manual ? 'New order (ship it yourself): ' : 'New order: ')
                .$this->order->order_number.' — '.$total,
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'chargefield-store::mail.new-order-alert');
    }
}
