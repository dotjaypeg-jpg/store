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
| `Masters\MasterFile` | `master_files` | each site's own screens (see Masters) |

The store tables already exist on each site. The package's migrations add
`blanks.catalog_id`, `products.carried_catalog_id` and the `master_files` table.

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
| `catalog.url` / `catalog.secret` | unset | Studio's base URL + its `CATALOG_SECRET`: turns on shared blanks |
| `catalog.product_snapshot` | `null` | one product's snapshot for Studio's index |
| `catalog.carry` / `catalog.print_file` | `null` | carrying products across stores (see below) |
| `studio_clients.models` | `[]` | models `studio:link-clients` matches to Studio clients by name |

## Shared blanks (Studio catalogue)

Studio holds the master blanks (Catalogue -> Blanks). With `STORE_CATALOG_URL`
and `STORE_CATALOG_SECRET` set, `store:sync-blanks` runs hourly:

1. **Offer**: any local blank without a `catalog_id` is sent to Studio, which
   adopts a same-named one (name + category) or creates it.
2. **Pull**: every master blank is written into the local row linked to it.
   Studio's copy wins; local ids never change, so `products.blank_id` holds.

The package migration adds `blanks.catalog_id`. Wire the admin once:
`BlankCatalog::locked($record)` makes a Studio blank read-only,
`BlankCatalog::editOnStudioAction()` links to it on Studio, and
`BlankCatalog::syncAction()` is a "Sync now" button for the list.

## Products in Studio's index

Products are still edited on their store. With `store.catalog.product_snapshot`
set (a `[Class::class, 'method']` returning one product's snapshot: see
`ProductReport` for the keys), `store:report-products` runs hourly and sends
every product to Studio's Catalogue -> Products, under the sales store key.
Products missing from a report are marked removed on Studio, not deleted.

Every sale sent to Studio also carries `lines` (product_id, title,
variant_title, quantity, amount_cents), so Studio knows what sold per product.
The first product report re-sends the sale history once to fill those in.

A site's `catalog` block replaces the package's, so spell out `url` and
`secret` next to `product_snapshot`.

## Carrying another store's products

A product keeps one home store. In Studio, Products -> "Carry on…" lists it on
another store too. Two callables in the `catalog` block switch it on:

- `carry`: `fn (array $listing, ?Model $existing): Model` writes the local
  copy from Studio's listing (title, description, images, variants). New copies
  land as drafts; a refresh keeps the local status and price overrides.
  `store:sync-carried` runs hourly at :20, and a listing that's switched off
  puts its copy back to draft.
- `print_file`: `fn (Model $product): ?string` returns a short-lived signed
  URL to one of this store's own print files. It turns on
  `GET /api/store/print-file/{id}` (bearer: the catalogue secret), which Studio
  calls when another store has an order to print.

At order time the carrying store calls `Carrying::printFileUrl($product)`,
which asks Studio, which asks the home store. The package migration adds
`products.carried_catalog_id` (Studio's id for the original), and the product
snapshot should send it as `carried_catalog_id`. Only print-on-demand products
can be carried: Studio won't list self-held stock.

## Masters

Every site keeps its private masters the same way: one `master_files` table
(the package migration creates it), one row per file, owned by any model that
uses `Masters\HasMasters`. A row's `role` says what the file is for: `poster`
(full artwork), `apparel` (garment design), `apparel_print` (the lean print
copy), or a commission's `onesheet`, `teaser`, `alt`, `character`, `festival`.

- Save through `Masters\MasterVault`. `store()` takes an upload, names it
  from the owner (never the uploaded name), takes the extension from the
  file's bytes, and adds `-2`, `-3`… on a clash. `putBytes()` writes a master
  made in code to an exact path. Both record size, sha256 and pixels.
- Read with `$model->masterFile('poster')`, `->masterFileUrl('poster', 60)`
  (a signed link, null on the local dev disk), and the `withMaster('poster')`
  / `withoutMaster('poster')` query scopes.
- `recordMaster()` replaces a role's one master; `addMaster()` adds another.
  Deleting a row deletes its file unless another row uses the same path.
- `php artisan masters:verify` checks every file is on its disk and fills in
  missing sizes; `--checksums` also reads files with no sha256 yet.

Disks, in `config/filesystems.php`:

```php
'masters' => \Chargefield\Store\Masters\Masters::diskConfig(),
'backups' => \Chargefield\Store\Masters\Masters::backupsConfig(),
```

Both read the same env names everywhere: `MASTERS_BUCKET`,
`MASTERS_ACCESS_KEY_ID`, `MASTERS_SECRET_ACCESS_KEY`, `MASTERS_DEFAULT_REGION`,
`MASTERS_ENDPOINT`, and the `BACKUP_*` set. No bucket means a local folder
under `storage/app`. The older `MASTERS_DRIVER`, `MASTERS_KEY`,
`MASTERS_SECRET` and `MASTERS_REGION` still work.

## Studio clients

Studio PM keeps the one client list. A record made for a client (a chargefield
client, a johngodfrey Work) stores that client's Studio id in a nullable
`studio_client_id` column, so the same client reads the same on every site.

- `Studio\StudioClients::all()` / `options()` / `name($id)` / `url($id)` read
  Studio's `GET /api/catalog/clients` (catalogue secret), cached five minutes.
  Only business names come over. Unconfigured = empty.
- `Studio\StudioClients::match($name)` finds the one client with that name after
  normalising (legal suffixes and punctuation dropped), or null.
- `Studio\LinksStudioClient` (trait) adds `studioClientName()`, `studioClientUrl()`
  and `forStudioClient($id)`. Override `studioClientNameColumn()` when the
  model's own client-name column isn't `client`.
- `php artisan studio:link-clients [--write]` matches unlinked rows of the models
  in `store.studio_clients.models` by name. Dry run unless `--write`.

## Writing package code

Sites hand Filament their own subclasses (`App\Models\Order`), so in resource
closures take the row **by name, untyped**: `fn ($record) => …`. Filament only
injects a typed parameter like `fn (Order $r)` when the type is the record's
exact class; for a subclass it builds an empty `Order` instead, and the closure
silently sees null fields.

## Releasing a change

Commit, then tag the next 1.x (`git tag v1.1.1 && git push --tags`). Sites
require `^1.0`, so `composer update chargefield/store` picks it up.
