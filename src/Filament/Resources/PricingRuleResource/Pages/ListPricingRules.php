<?php

namespace Chargefield\Store\Filament\Resources\PricingRuleResource\Pages;

use Chargefield\Store\Filament\Resources\PricingRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPricingRules extends ListRecords
{
    protected static string $resource = PricingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('New price rule')];
    }
}
