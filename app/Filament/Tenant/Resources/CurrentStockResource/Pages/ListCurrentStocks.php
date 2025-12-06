<?php

namespace App\Filament\Tenant\Resources\CurrentStockResource\Pages;

use App\Filament\Tenant\Resources\CurrentStockResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCurrentStocks extends ListRecords
{
    protected static string $resource = CurrentStockResource::class;

    protected static ?string $title = 'Current Stock Levels';
    protected function getHeaderActions(): array
    {
        return [];
    }
}
