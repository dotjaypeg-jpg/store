<?php

namespace Chargefield\Store\Filament\Resources\StockistResource\Pages;

use Chargefield\Store\Filament\Resources\StockistResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStockists extends ListRecords
{
    protected static string $resource = StockistResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
