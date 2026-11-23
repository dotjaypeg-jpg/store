<?php

namespace Chargefield\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Config-driven lookups shared by the store models and resources. */
class Store
{
    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    public static function model(string $key): string
    {
        return (string) config("store.models.$key");
    }

    /** @return array<string, string> */
    public static function productCategories(): array
    {
        $resolver = config('store.product_categories');

        return $resolver ? (array) call_user_func($resolver) : [];
    }

    public static function imageUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $resolver = config('store.image_url');

        return $resolver
            ? call_user_func($resolver, $path)
            : Storage::disk('public')->url($path);
    }

    public static function currency(): string
    {
        return (string) config('store.currency', 'CAD');
    }

    /**
     * The printable mailing-slip URL for an order, via the host app's
     * store.mailing_slip.url resolver. Null when no resolver is configured (the
     * admin "Print mailing slip" action then stays hidden).
     */
    public static function mailingSlipUrl(Model $order): ?string
    {
        $resolver = config('store.mailing_slip.url');

        return $resolver ? call_user_func($resolver, $order) : null;
    }
}
