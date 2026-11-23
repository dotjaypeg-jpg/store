@php
    $money = fn ($n) => '$'.number_format((float) $n, 2);

    $totals = ['Subtotal: '.$money($order->subtotal)];
    if ((float) $order->discount_total > 0) {
        $totals[] = 'Discount: -'.$money($order->discount_total);
    }
    $totals[] = 'Shipping: '.((float) $order->shipping_total > 0 ? $money($order->shipping_total) : 'Free');
    if ((float) $order->tax_total > 0) {
        $totals[] = 'Tax: '.$money($order->tax_total);
    }

    // Items the owner ships themselves — no provider prints or mails these, so they
    // sit until handled. Called out below so a self-fulfilled order can't be missed.
    $manualItems = $order->items->filter(fn ($item) => ($item->provider ?: 'gelato') === 'manual');

    $addressLines = [];
    if ($order->shipping_address) {
        $a = $order->shipping_address;
        $addr = $a['address'] ?? $a;
        $addressLines = array_values(array_filter([
            $a['name'] ?? $order->customer_name,
            $addr['line1'] ?? null,
            $addr['line2'] ?? null,
            trim(($addr['city'] ?? '').' '.($addr['state'] ?? '').' '.($addr['postal_code'] ?? '')),
            $addr['country'] ?? null,
        ]));
    }
@endphp
@component('mail::message')
# New order — {{ $order->order_number }}

A paid order just came in{{ $order->customer_name ? ' from '.$order->customer_name : '' }}.

@if ($manualItems->isNotEmpty())
@component('mail::panel')
**You ship this one yourself.** {{ $manualItems->count() === $order->items->count() ? 'Every item' : $manualItems->count().' item'.($manualItems->count() === 1 ? '' : 's') }} on this order {{ $manualItems->count() === 1 ? 'is' : 'are' }} self-fulfilled — no print provider will handle or mail {{ $manualItems->count() === 1 ? 'it' : 'them' }}. Print, pack, and ship, then mark the order shipped.
@endcomponent
@endif

@component('mail::table')
| Item | Qty | Price | Fulfilment |
| :--- | :-: | ----: | :--------- |
@foreach ($order->items as $item)
| {{ $item->title }}{{ $item->variant_title ? ' - '.$item->variant_title : '' }} | {{ $item->quantity }} | {{ $money((float) $item->price * $item->quantity) }} | {{ ($item->provider ?: 'gelato') === 'manual' ? 'You ship' : ucfirst($item->provider ?: 'gelato') }} |
@endforeach
@endcomponent

{!! implode('<br>', array_map('e', $totals)) !!}<br>
**Total: {{ $money($order->total) }} {{ $order->currency }}**

@if ($order->email)
**Customer**<br>
{{ $order->customer_name ?: '—' }}<br>
{{ $order->email }}
@endif

@if ($addressLines)
**Ship to**<br>
{!! implode('<br>', array_map('e', $addressLines)) !!}
@endif

@component('mail::button', ['url' => url('/admin/orders/'.$order->getKey())])
Open the order
@endcomponent
@endcomponent
