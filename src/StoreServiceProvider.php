<?php

namespace Chargefield\Store;

use Chargefield\Store\Catalog\BlankSync;
use Chargefield\Store\Console\ReportSales;
use Chargefield\Store\Console\SyncBlanks;
use Chargefield\Store\Sales\StudioSales;
use Illuminate\Console\Scheduling\Schedule;
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

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([SyncBlanks::class, ReportSales::class]);

            // Hourly pull from Studio, only on sites that set the catalogue up.
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
                if (BlankSync::enabled()) {
                    $schedule->command('store:sync-blanks')->hourly()->withoutOverlapping()->onOneServer();
                }
                // Nightly catch-up for any sale a queued send missed.
                if (StudioSales::enabled()) {
                    $schedule->command('store:report-sales')->dailyAt('03:30')->withoutOverlapping()->onOneServer();
                }
            });
        }
    }
}
