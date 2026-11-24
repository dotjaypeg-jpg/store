<?php

namespace Chargefield\Store\Filament\Resources\BlankResource\Pages;

use Chargefield\Store\Filament\Resources\BlankResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlank extends EditRecord
{
    protected static string $resource = BlankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
