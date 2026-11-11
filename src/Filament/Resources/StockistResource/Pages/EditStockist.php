<?php

namespace Chargefield\Store\Filament\Resources\StockistResource\Pages;

use Chargefield\Store\Filament\Resources\StockistResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStockist extends EditRecord
{
    protected static string $resource = StockistResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
