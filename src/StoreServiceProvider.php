<?php

namespace Chargefield\Store;

use Illuminate\Support\ServiceProvider;

class StoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/store.php', 'store');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/store.php' => $this->app->configPath('store.php'),
        ], 'store-config');
    }
}
