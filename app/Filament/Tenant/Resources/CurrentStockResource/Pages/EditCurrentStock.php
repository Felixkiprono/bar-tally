<?php

namespace App\Filament\Tenant\Resources\CurrentStockResource\Pages;

use App\Filament\Tenant\Resources\CurrentStockResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCurrentStock extends EditRecord
{
    protected static string $resource = CurrentStockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
