<?php

namespace App\Filament\Tenant\Resources\CurrentStockResource\Pages;

use App\Filament\Tenant\Resources\CurrentStockResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListCurrentStocks extends ListRecords
{

    protected static string $resource = CurrentStockResource::class;

    protected static ?string $title = 'Current Stock Levels';
    protected function getHeaderActions(): array
    {
        $service = app(\App\Services\Inventory\InventoryService::class);

        return [
            Action::make('downloadReorder')
                ->label('Download Items Low on Stock')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->action(fn() => $service->exportBelowReorderCsv()),
        ];
    }


}
