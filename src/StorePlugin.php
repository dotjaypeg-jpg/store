<?php

namespace Chargefield\Store;

use Chargefield\Store\Filament\Resources\OrderResource;
use Chargefield\Store\Filament\Resources\PricingRuleResource;
use Chargefield\Store\Filament\Resources\StockistResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Registers the shared store admin (Orders, Pricing, Stockists) on a panel:
 *
 *     ->plugin(StorePlugin::make())
 */
class StorePlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'chargefield-store';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            OrderResource::class,
            PricingRuleResource::class,
            StockistResource::class,
        ]);
    }

    public function boot(Panel $panel): void {}
}
