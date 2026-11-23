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
 * @property string|null $carrier
 * @property string|null $service
 * @property string $status
 * @property string|null $provider_order_id
 * @property string|null $tracking_number
 * @property string|null $tracking_url
 * @property string|null $label_path
 * @property string|null $label_format
 * @property float|null $label_cost
 * @property string|null $label_currency
 * @property Carbon|null $shipped_at
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
        'label_cost' => 'decimal:2',
        'shipped_at' => 'datetime',
        'shipped_notified_at' => 'datetime',
    ];

    /** True once a bought shipping label has been stored for this fulfilment. */
    public function hasLabel(): bool
    {
        return filled($this->label_path);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Store::model('order'), 'order_id');
    }
}
