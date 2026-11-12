<?php

namespace Chargefield\Store;

use Chargefield\Store\Catalog\BlankSync;
use Chargefield\Store\Catalog\Carrying;
use Chargefield\Store\Catalog\ProductReport;
use Chargefield\Store\Console\LinkStudioClients;
use Chargefield\Store\Console\ReportProducts;
use Chargefield\Store\Console\ReportSales;
use Chargefield\Store\Console\SyncBlanks;
use Chargefield\Store\Console\SyncCarried;
use Chargefield\Store\Console\VerifyMasters;
use Chargefield\Store\Http\PrintFileController;
use Chargefield\Store\Sales\StudioSales;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
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

        // Studio fetches this store's print files here when another store carries
        // one of its products. Bearer-authed; only with print_file set.
        if (filled(config('store.catalog.print_file')) && ! $this->app->routesAreCached()) {
            Route::get('/api/store/print-file/{id}', PrintFileController::class)
                ->whereNumber('id')
                ->middleware('throttle:120,1')
                ->name('store.print-file');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([SyncBlanks::class, ReportSales::class, ReportProducts::class, SyncCarried::class, VerifyMasters::class, LinkStudioClients::class]);

            // Hourly pull from Studio, only on sites that set the catalogue up.
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
                if (BlankSync::enabled()) {
                    $schedule->command('store:sync-blanks')->hourly()->withoutOverlapping()->onOneServer();
                }
                // Products into Studio's index, just after the blanks so new blank links resolve.
                if (ProductReport::enabled()) {
                    $schedule->command('store:report-products')->hourlyAt(10)->withoutOverlapping()->onOneServer();
                }
                // Products carried from other stores, after the reports so new listings are in.
                if (Carrying::enabled()) {
                    $schedule->command('store:sync-carried')->hourlyAt(20)->withoutOverlapping()->onOneServer();
                }
                // Nightly catch-up for any sale a queued send missed.
                if (StudioSales::enabled()) {
                    $schedule->command('store:report-sales')->dailyAt('03:30')->withoutOverlapping()->onOneServer();
                }
            });
        }
    }
}
