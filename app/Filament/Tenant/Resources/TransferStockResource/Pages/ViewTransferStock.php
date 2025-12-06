<?php

namespace App\Filament\Tenant\Resources\TransferStockResource\Pages;

use App\Filament\Tenant\Resources\TransferStockResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTransferStock extends ViewRecord
{
    protected static string $resource = TransferStockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
