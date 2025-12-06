<?php

namespace App\Filament\Tenant\Resources\TransferStockResource\Pages;

use App\Filament\Tenant\Resources\TransferStockResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransferStock extends EditRecord
{
    protected static string $resource = TransferStockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
