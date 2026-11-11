# chargefield/store

The shared store admin for every Chargefield site (chargefield, johngodfrey, and
any future client site with a shop): **Orders**, **Pricing** (the price book)
and **Stockists**, as Eloquent models plus Filament resources. Fix or improve a
store screen once here, release, and `composer update` each site.

It sits next to `chargefield/fulfillment` (print-provider drivers) and
`chargefield/admin-theme` (the look). Like those, it is a plain Composer library:
no hosting, no Laravel Cloud service. The repo is public: keep it free of
secrets and client data.

## What's shared

| Model | Table | Admin |
|-------|-------|-------|
| `Order` | `orders` | Store → Orders (list + view; created by checkout only) |
| `OrderItem` | `order_items` | shown inside an order |
| `OrderFulfillment` | `order_fulfillments` | shown inside an order |
| `PricingRule` | `pricing_rules` | Store → Pricing |
| `Stockist` | `stockists` | Portfolio → Stockists |

The package ships **no migrations**: the tables already exist on each site.

## Install

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/dotjaypeg-jpg/store.git" }
]
```

```bash
composer require chargefield/store:^1.0
```

Register the resources on the panel:

```php
->plugin(\Chargefield\Store\StorePlugin::make())
```

Keep a thin subclass per model, so the site's own code, factories and tests keep
using `App\Models\*`:

```php
namespace App\Models;

class Order extends \Chargefield\Store\Models\Order {}
```

A site adds its own extras on its subclass (johngodfrey's `Stockist` adds
`editions()`; the Stockists list then shows an Editions count).

## Per-site settings (`config/store.php`)

| key | default | meaning |
|-----|---------|---------|
| `models.*` | `App\Models\…` | the site's subclasses (+ its `Product`, `ProductVariant`) |
| `order_number_prefix` | `JG-` | new order numbers: `PREFIX` + 8 chars |
| `currency` | `CAD` | money shown in the admin |
| `own_store.slug/name/url` | John Godfrey shop | `Stockist::ownStore()` |
| `product_categories` | `null` | `[Class::class, 'method']` returning key ⇒ label, for Pricing |
| `image_url` | public disk | `[Class::class, 'method']` turning a stored path into a URL |
| `navigation.*` | Store / Store / Portfolio | nav group per screen |

## Writing package code

Sites hand Filament their own subclasses (`App\Models\Order`), so in resource
closures take the row **by name, untyped**: `fn ($record) => …`. Filament only
injects a typed parameter like `fn (Order $r)` when the type is the record's
exact class; for a subclass it builds an empty `Order` instead, and the closure
silently sees null fields.

## Releasing a change

Commit, then tag the next 1.x (`git tag v1.0.1 && git push --tags`). Sites
require `^1.0`, so `composer update chargefield/store` picks it up.
