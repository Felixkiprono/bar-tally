<?php

namespace App\Filament\Tenant\Resources\CurrentStockResource\Pages;

use App\Filament\Tenant\Resources\CurrentStockResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCurrentStock extends ViewRecord
{
    protected static string $resource = CurrentStockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
