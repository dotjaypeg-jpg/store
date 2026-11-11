<?php

namespace Chargefield\Store\Models;

use Chargefield\Store\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property string $provider
 * @property string $status
 * @property string|null $provider_order_id
 * @property string|null $tracking_number
 * @property string|null $tracking_url
 * @property Carbon|null $shipped_notified_at
 * @property array<string, mixed>|null $response
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order|null $order
 */
class OrderFulfillment extends Model
{
    protected $table = 'order_fulfillments';

    protected $guarded = [];

    protected $casts = [
        'response' => 'array',
        'shipped_notified_at' => 'datetime',
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Store::model('order'), 'order_id');
    }
}
