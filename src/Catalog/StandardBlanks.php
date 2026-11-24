<?php

namespace Chargefield\Store\Catalog;

use Chargefield\Store\Store;

/**
 * The canonical stock/blank definitions shared across every Chargefield site.
 *
 * The same physical blank (a tee, a poster stock) has the same material and the
 * same care wherever it's sold, so that copy lives here once rather than being
 * re-typed per site. `store:seed-blanks` upserts these into a site's `blanks`
 * table, keyed by name. A site may still edit a row afterwards, and a wired-up
 * Studio catalogue (BlankSync) will override with the master copy.
 */
class StandardBlanks
{
    /**
     * @return array<int, array{name: string, category: string, is_default: bool, blurb: string, care: array<int, array{title: string, body: string}>, size_guide?: array<int, array<string, string>>}>
     */
    public static function all(): array
    {
        $garmentCare = [
            ['title' => 'Wash', 'body' => 'Machine wash cold, inside out, with like colours.'],
            ['title' => 'Dry', 'body' => 'Tumble dry low or hang to dry. Lower heat keeps the print sharp.'],
            ['title' => 'Iron', 'body' => 'Warm iron inside out — never directly on the print.'],
            ['title' => "Don't", 'body' => 'No bleach, no dry cleaning.'],
        ];

        $teeSizes = [
            ['label' => 'S', 'chest' => '18', 'length' => '28'],
            ['label' => 'M', 'chest' => '20', 'length' => '29'],
            ['label' => 'L', 'chest' => '22', 'length' => '30'],
            ['label' => 'XL', 'chest' => '24', 'length' => '31'],
            ['label' => '2XL', 'chest' => '26', 'length' => '32'],
        ];

        $paperCare = [
            ['title' => 'Handle', 'body' => 'Hold by the edges with clean, dry hands. Prints are unframed.'],
            ['title' => 'Frame', 'body' => 'Frame behind glass or acrylic, away from direct sunlight, to keep colours true.'],
            ['title' => 'Store', 'body' => 'Keep flat or rolled in its tube, somewhere cool and dry.'],
        ];

        return [
            [
                'name' => 'Heavyweight Cotton Tee',
                'category' => 'apparel',
                'is_default' => true,
                'blurb' => 'Printed to order on a 100% ring-spun combed cotton tee, ~6.1 oz — a proper heavyweight with a boxy, true-to-size fit and a soft hand. Water-based inks, so the print sits in the fabric rather than on top of it.',
                'care' => $garmentCare,
                'size_guide' => $teeSizes,
            ],
            [
                'name' => 'Pullover Hoodie',
                'category' => 'hoodie',
                'is_default' => true,
                'blurb' => 'A midweight 8.5 oz cotton-rich fleece hoodie with a brushed-soft inside, double-lined hood and a roomy kangaroo pocket. Printed to order.',
                'care' => $garmentCare,
            ],
            [
                'name' => 'Giclée Art Print',
                'category' => 'print',
                'is_default' => true,
                'blurb' => 'A giclée print on 200gsm acid-free, archival matte fine-art stock. Printed to order with pigment inks rated to last decades without fading. Shipped flat or rolled; unframed.',
                'care' => $paperCare,
            ],
            [
                'name' => 'Offset Poster Stock',
                'category' => 'poster',
                'is_default' => true,
                'blurb' => 'Printed to order on a weighty 170gsm silk poster stock with a smooth, low-glare finish. Shipped rolled in a sturdy tube; unframed.',
                'care' => $paperCare,
            ],
            [
                'name' => 'Die-Cut Vinyl Sticker',
                'category' => 'sticker',
                'is_default' => true,
                'blurb' => 'Durable die-cut vinyl with a matte laminate — waterproof, UV-resistant and dishwasher-safe once applied. Sold as a pack.',
                'care' => [
                    ['title' => 'Apply', 'body' => 'Stick to a clean, dry, smooth surface and press firmly from the centre out.'],
                    ['title' => 'Lasts', 'body' => 'Indoor or outdoor; holds up to weather and the odd dishwasher cycle.'],
                ],
            ],
        ];
    }

    /**
     * Upsert the canonical blanks into the site's `blanks` table, keyed by name.
     * Returns [created, updated].
     *
     * @return array{created: int, updated: int}
     */
    public static function install(): array
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
        $model = Store::model('blank');
        $created = 0;
        $updated = 0;

        // Only install blanks for categories this site actually sells (when it
        // declares them), so a site never gets an orphan stock it has no products
        // for. An unconfigured category list installs the whole set.
        $categories = array_keys(Store::productCategories());

        foreach (self::all() as $blank) {
            if ($categories !== [] && ! in_array($blank['category'], $categories, true)) {
                continue;
            }

            $existing = $model::query()->where('name', $blank['name'])->first();

            if ($existing) {
                $existing->fill($blank)->save();
                $updated++;
            } else {
                $model::query()->create($blank);
                $created++;
            }
        }

        if (method_exists($model, 'forgetDefaults')) {
            $model::forgetDefaults();
        }

        return ['created' => $created, 'updated' => $updated];
    }
}
