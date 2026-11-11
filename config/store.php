<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Model classes
    |--------------------------------------------------------------------------
    |
    | Each site keeps thin subclasses (App\Models\Order extends
    | Chargefield\Store\Models\Order) so its own code, factories and tests keep
    | using App\Models\*. The package resolves every relation and resource
    | through this map, so it always hands back the site's classes.
    | `product` / `product_variant` are the site's own catalogue models.
    |
    */
    'models' => [
        'order' => 'App\Models\Order',
        'order_item' => 'App\Models\OrderItem',
        'order_fulfillment' => 'App\Models\OrderFulfillment',
        'pricing_rule' => 'App\Models\PricingRule',
        'stockist' => 'App\Models\Stockist',
        'product' => 'App\Models\Product',
        'product_variant' => 'App\Models\ProductVariant',
    ],

    // Prefix for new order numbers (PREFIX-XXXXXXXX).
    'order_number_prefix' => env('STORE_ORDER_PREFIX', 'JG-'),

    // Currency for line-item prices and pricing-rule costs in the admin.
    'currency' => env('STORE_CURRENCY', 'CAD'),

    // The site's own shop, as a stockist (Stockist::ownStore()).
    'own_store' => [
        'slug' => env('STORE_OWN_SLUG', 'john-godfrey-store'),
        'name' => env('STORE_OWN_NAME', 'John Godfrey'),
        'url' => env('STORE_OWN_URL', 'https://johngodfrey.com/shop'),
    ],

    /*
    | Callables the site supplies (as [Class::class, 'method'] so config can
    | be cached):
    |  - product_categories: fn (): array<string, string>  key => label
    |  - image_url: fn (?string $path): ?string  public URL for a stored image
    |    (null = the public disk's URL).
    */
    'product_categories' => null,
    'image_url' => null,

    // Admin navigation groups.
    'navigation' => [
        'orders' => 'Store',
        'pricing' => 'Store',
        'stockists' => 'Portfolio',
    ],

];
