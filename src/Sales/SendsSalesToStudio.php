<?php

namespace Chargefield\Store\Sales;

use Illuminate\Database\Eloquent\Model;

/**
 * For a model implementing ReportsToStudio: when it's created or one of its
 * watched attributes changes, queue it to be (re-)sent to Studio. The job reads
 * the model fresh, so a burst of saves sends its final state.
 *
 * @mixin Model
 */
trait SendsSalesToStudio
{
    public static function bootSendsSalesToStudio(): void
    {
        static::saved(function (Model $model) {
            if (! $model instanceof ReportsToStudio || ! StudioSales::enabled()) {
                return;
            }

            if ($model->wasRecentlyCreated || $model->wasChanged($model->studioSaleWatched())) {
                ReportSaleToStudio::dispatch($model::class, $model->getKey())->afterCommit();
            }
        });
    }
}
