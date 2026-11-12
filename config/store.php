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
        'blank' => 'App\Models\Blank',
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

    /*
    |--------------------------------------------------------------------------
    | Shared catalogue (Studio)
    |--------------------------------------------------------------------------
    |
    | Studio holds the master blanks. With both set, `store:sync-blanks` runs
    | hourly: it offers Studio any local blank it doesn't know yet, then pulls
    | every master blank into the local table. `url` is Studio's base URL; the
    | secret matches Studio's CATALOG_SECRET. Unset = the site keeps its own
    | blanks, as before.
    |
    */
    'catalog' => [
        'url' => env('STORE_CATALOG_URL'),
        'secret' => env('STORE_CATALOG_SECRET'),

        // fn (Model $product): array  what Studio's product index gets for one
        // product (see ProductReport for the keys). Set it and `store:report-products`
        // runs hourly; null = products aren't reported.
        'product_snapshot' => null,

        // Carrying another store's products (listed in Studio, Products ->
        // "Carry on"). Both callables, set to take part:
        //  - carry: fn (array $listing, ?Model $existing): Model  write the local
        //    copy from Studio's listing (new ones as drafts; keep the local status
        //    and price overrides on refresh). `store:sync-carried` runs hourly.
        //  - print_file: fn (Model $product): ?string  a short-lived signed URL to
        //    one of this store's own print files, for an order on another store.
        //    Turns on GET /api/store/print-file/{id}.
        'carry' => null,
        'print_file' => null,
    ],


    /*
    |--------------------------------------------------------------------------
    | Sales into Studio's revenue ledger
    |--------------------------------------------------------------------------
    |
    | With the catalogue's Studio URL + secret above and a store key here, every
    | paid live order (and anything else listed in `models`) is sent to Studio
    | as it's paid, refunded or cancelled, so the business-wide books include
    | store income. `store` is the key Studio files this store under (e.g.
    | "johngodfrey"). `models` are what `store:report-sales` sweeps: model-map
    | keys or class names implementing Sales\ReportsToStudio.
    |
    */
    'sales' => [
        'store' => env('STORE_SALES_KEY'),
        'models' => ['order'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Masters
    |--------------------------------------------------------------------------
    |
    | Private master files (print masters, apparel designs, commission
    | one-sheets) in one `master_files` table, owned by any model that uses
    | Masters\HasMasters. `disk` is the private disk they live on; `model` lets
    | a site use its own subclass (for a policy or a download route).
    |
    */
    'masters' => [
        'disk' => 'masters',
        'model' => \Chargefield\Store\Masters\MasterFile::class,
    ],

];
