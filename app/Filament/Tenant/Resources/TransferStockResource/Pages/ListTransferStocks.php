<?php

namespace App\Filament\Tenant\Resources\TransferStockResource\Pages;

use App\Filament\Tenant\Resources\TransferStockResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTransferStocks extends ListRecords
{
    protected static string $resource = TransferStockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
